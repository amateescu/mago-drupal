<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Closure;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

use function basename;
use function chmod;
use function fclose;
use function file_get_contents;
use function file_put_contents;
use function filemtime;
use function fileowner;
use function fileperms;
use function filesize;
use function flock;
use function fopen;
use function function_exists;
use function getenv;
use function getmypid;
use function glob;
use function is_dir;
use function is_link;
use function is_object;
use function is_string;
use function microtime;
use function mkdir;
use function posix_geteuid;
use function preg_match;
use function preg_quote;
use function rename;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function serialize;
use function set_error_handler;
use function sha1;
use function str_contains;
use function str_starts_with;
use function strlen;
use function substr;
use function sys_get_temp_dir;
use function time;
use function unlink;
use function unserialize;

use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

/**
 * Keeps parsed disk-backed indexes between runs and between workers.
 *
 * Every worker process parses the same services YAML, config schema and
 * api.php files on its first request. The parse costs about half a second per
 * worker on a core checkout; loading the serialized result costs a few
 * milliseconds. Entries are keyed by the paths, modification times and sizes
 * of the input files, so an edited file misses the cache. A same-size edit
 * within the second of the previous read is the one case that would not, so
 * files touched in the last two seconds are not cached at all. Every entry
 * name also carries a hash of this package's own code (see code()), so an
 * update never reads a payload an older version wrote.
 *
 * Metadata-backed indexes cannot outlive a run, but within one run every
 * worker builds the same ones from the same frozen codebase. `shared()` lets
 * the first worker build and the others load its result.
 *
 * `MAGO_DRUPAL_CACHE=0` turns the cache off; any other value is the directory
 * to use. The default sits under the system temporary directory, one per
 * user, and is only used while it belongs to that user (see trusted()).
 * `scoped()` gives each Drupal root its own subdirectory, so the
 * one-entry-per-kind sweep never crosses roots.
 *
 * A worker registers its plugins before the SDK sends stray output to
 * stderr, and stdout is the protocol stream. Filesystem calls that race
 * another worker go through quietly(), so an expected failure never prints a
 * warning.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class DiskCache
{
    private const ENVIRONMENT = 'MAGO_DRUPAL_CACHE';

    /**
     * The layout of the cache directory. Payloads follow the code itself
     * (see code()), so this only changes when the layout does.
     */
    private const LAYOUT = 3;

    /**
     * One run's shared entries carry the host process id, so entries of a
     * finished run can be told apart and removed.
     */
    private const RUN_PREFIX = 'run-';

    /**
     * A run entry is only read while it is this fresh, and is dropped once it
     * is older. Every worker of a run asks for an index within seconds of the
     * others, and on a system that publishes no process start times this is
     * what keeps a run from reading an entry left by an older one whose host
     * had the same process id.
     */
    private const RUN_FRESHNESS = 1_800;

    private const RECENT = 2;

    /**
     * Seconds between two attempts at a lock another worker holds.
     */
    private const LOCK_POLL = 0.005;

    /**
     * A waiter gives up after this many seconds and builds the value itself.
     * Only a worker that died mid-build or a build far slower than any
     * measured one gets here.
     */
    private const LOCK_WAIT = 120.0;

    /**
     * Code hash for entry names, computed once per process.
     */
    private static ?string $code = null;

    /**
     * Set once trusted() has found the owned directory safe.
     */
    private bool $trusted = false;

    /**
     * @param string|null $owned A directory in a shared temporary directory
     *   that must belong to this user before anything is read from or written
     *   under it.
     */
    public function __construct(
        private readonly string $directory,
        private readonly ?string $owned = null,
    ) {}

    /**
     * The cache the environment asks for, or null when it is switched off.
     */
    public static function fromEnvironment(): ?self
    {
        $setting = getenv(self::ENVIRONMENT);
        if ($setting === '0') {
            return null;
        }

        if (is_string($setting) && $setting !== '') {
            return new self($setting);
        }

        $user = function_exists('posix_geteuid') ? (string) posix_geteuid() : 'shared';
        $directory = sys_get_temp_dir() . '/mago-drupal-' . $user;

        return new self($directory, owned: $directory);
    }

    /**
     * A subdirectory for one Drupal root.
     */
    public function scoped(string $root): self
    {
        return new self($this->directory . '/v' . (string) self::LAYOUT . '-' . sha1($root), $this->owned);
    }

    /**
     * A key for the files as they are now: path, modification time and size.
     *
     * Null while one of them may still be changing, as far as a one-second
     * modification time can tell. The caller then parses without the cache,
     * since no later reader would come up with the same key.
     *
     * @param list<string> $paths
     */
    public static function fingerprint(array $paths): ?string
    {
        $recent = time() - self::RECENT;

        // A file can disappear between the listing and the stat, which then
        // counts as time and size 0.
        return self::quietly(static function () use ($paths, $recent): ?string {
            $lines = '';
            foreach ($paths as $path) {
                $mtime = (int) filemtime($path);
                if ($mtime >= $recent) {
                    return null;
                }

                $lines .= $path . '|' . (string) $mtime . '|' . (string) (int) filesize($path) . "\n";
            }

            return sha1($lines);
        });
    }

    /**
     * @param list<class-string> $classes Classes the stored value may contain.
     */
    public function get(string $kind, string $fingerprint, array $classes = []): mixed
    {
        if (!$this->trusted()) {
            return null;
        }

        // Another worker's sweep can remove the entry at any moment.
        $file = $this->file($kind, $fingerprint);
        $data = self::quietly(static fn(): string|false => file_get_contents($file));
        if ($data === false || $data === '') {
            return null;
        }

        /** @var mixed $value */
        $value = self::quietly(static fn(): mixed => unserialize($data, ['allowed_classes' => $classes]));

        return $value === false ? null : $value;
    }

    /**
     * Writes atomically and drops the same kind's entries for other
     * fingerprints, so a root's directory holds one entry per kind. Run
     * entries are dropped by dropFinishedRuns() instead.
     */
    public function set(string $kind, string $fingerprint, mixed $value): void
    {
        if (!$this->prepare()) {
            return;
        }

        $file = $this->file($kind, $fingerprint);
        if (!str_starts_with($fingerprint, self::RUN_PREFIX)) {
            $stale = glob($this->directory . '/' . $kind . '-*.cache');
            foreach ($stale === false ? [] : $stale as $entry) {
                // The rename below replaces the entry for this key in one
                // step, so a reader never finds it missing.
                if ($entry === $file || str_contains(basename($entry), '-' . self::RUN_PREFIX)) {
                    continue;
                }

                self::quietly(static fn(): bool => unlink($entry));
            }
        }

        $this->dropOrphanedTemporaries($kind);
        $temporary = $file . '.' . (string) getmypid() . '.tmp';
        $data = serialize($value);
        if (self::quietly(static fn(): int|false => file_put_contents($temporary, $data)) === false) {
            // A full disk leaves part of the file behind.
            self::quietly(static fn(): bool => unlink($temporary));

            return;
        }

        if (!self::quietly(static fn(): bool => rename($temporary, $file))) {
            self::quietly(static fn(): bool => unlink($temporary));
        }
    }

    /**
     * Builds a value once per run across worker processes. The first caller
     * holds a lock while it builds and writes; the others wait for it, then
     * read what it wrote.
     *
     * The wait never blocks the process. A build suspends on codebase
     * requests, and the worker answers those from the same event loop that
     * runs every other request, so a worker stuck in a blocking `flock()`
     * would also stop feeding the build it is waiting for. Two workers each
     * holding one index lock and waiting for the other's would then never
     * finish. Waiting through the loop keeps both builds fed.
     *
     * @template T of object
     * @param string $run Identifies the run and the codebase facts the value
     *   was built from; every worker derives the same key.
     * @param Closure(): T $build
     * @param list<class-string> $classes Classes the value may contain.
     * @return T
     */
    public function shared(string $kind, string $run, Closure $build, array $classes): object
    {
        $key = self::RUN_PREFIX . $run;
        /** @var T|null $ready */
        $ready = $this->runEntry($kind, $key, $classes);
        if ($ready !== null) {
            return $ready;
        }

        if (!$this->prepare()) {
            return $build();
        }

        $path = $this->file($kind, $key) . '.lock';
        $lock = self::quietly(static fn() => fopen($path, mode: 'c'));
        if ($lock === false) {
            return $build();
        }

        try {
            $deadline = microtime(as_float: true) + self::LOCK_WAIT;
            $blocked = false;
            while (!flock($lock, LOCK_EX | LOCK_NB, $blocked)) {
                /** @var T|null $ready */
                $ready = $this->runEntry($kind, $key, $classes);
                if ($ready !== null) {
                    return $ready;
                }

                // A filesystem without locking, such as some network mounts,
                // fails without ever being blocked. Building here costs the
                // duplicated work and nothing else.
                if (!$blocked || microtime(as_float: true) >= $deadline) {
                    return $build();
                }

                self::pause();
            }

            try {
                /** @var T|null $ready */
                $ready = $this->runEntry($kind, $key, $classes);
                if ($ready !== null) {
                    return $ready;
                }

                $value = $build();
                $this->dropFinishedRuns($kind, $key);
                $this->set($kind, $key, $value);

                return $value;
            } finally {
                flock($lock, LOCK_UN);
            }
        } finally {
            fclose($lock);
        }
    }

    /**
     * A run entry another worker wrote and this run may use.
     *
     * @param list<class-string> $classes Classes the value may contain.
     */
    private function runEntry(string $kind, string $key, array $classes): ?object
    {
        $file = $this->file($kind, $key);
        $mtime = self::quietly(static fn(): int|false => filemtime($file));
        if ($mtime === false || $mtime < (time() - self::RUN_FRESHNESS)) {
            return null;
        }

        /** @var mixed $value */
        $value = $this->get($kind, $key, $classes);

        return is_object($value) ? $value : null;
    }

    /**
     * Gives the event loop the turn for one poll interval.
     */
    private static function pause(): void
    {
        /** @var Suspension<null> $suspension */
        $suspension = EventLoop::getSuspension();
        EventLoop::delay(self::LOCK_POLL, static function () use ($suspension): void {
            $suspension->resume();
        });
        $suspension->suspend();
    }

    /**
     * Creates the directory, readable by this user only.
     *
     * The owned directory is created and checked before anything under it,
     * since taking it back empties it (see trusted()). Another worker may
     * create either directory first; the second checks cover that.
     */
    private function prepare(): bool
    {
        $owned = $this->owned;
        if ($owned !== null && !is_dir($owned)) {
            self::quietly(static fn(): bool => mkdir($owned, permissions: 0o700, recursive: true));
        }

        if (!$this->trusted()) {
            return false;
        }

        $directory = $this->directory;

        return (
            is_dir($directory)
            || self::quietly(static fn(): bool => mkdir($directory, permissions: 0o700, recursive: true))
            || is_dir($directory)
        );
    }

    /**
     * Whether entries may be read and written. The default cache sits in the
     * shared temporary directory, where another local user could create the
     * directory first and plant entries or links in it. It is used only while
     * it is a real directory that belongs to this user, and one that others
     * can write to is taken back first (see reclaim()). Without the posix
     * extension there is no user to compare, and the temporary directory is a
     * per-user one anyway.
     */
    private function trusted(): bool
    {
        if ($this->trusted || $this->owned === null || !function_exists('posix_geteuid')) {
            return true;
        }

        $owned = $this->owned;
        if (
            is_link($owned)
            || !is_dir($owned)
            || self::quietly(static fn(): int|false => fileowner($owned)) !== posix_geteuid()
        ) {
            return false;
        }

        $open = ((int) self::quietly(static fn(): int|false => fileperms($owned)) & 0o022) !== 0;
        $this->trusted = !$open || self::reclaim($owned);

        return $this->trusted;
    }

    /**
     * Closes a directory of this user's to everyone else, then deletes what
     * is in it, since any of it may have been planted while it was open.
     * Closing it first means nothing more can be added. Links are removed,
     * never followed. An entry that cannot be deleted, such as another
     * user's subdirectory, leaves the cache refused.
     *
     * Another worker may be taking it back at the same time, or already
     * writing to it once it is closed. Deleting an entry it wrote only costs
     * a cache miss.
     */
    private static function reclaim(string $directory): bool
    {
        return self::quietly(static fn(): bool => chmod($directory, permissions: 0o700)) && self::clear($directory);
    }

    /**
     * Deletes everything under a directory without following links.
     */
    private static function clear(string $directory): bool
    {
        $entries = self::quietly(static fn(): array|false => scandir($directory));
        if ($entries === false) {
            return false;
        }

        $cleared = true;
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory . '/' . $entry;
            $removed = is_dir($path) && !is_link($path)
                ? self::clear($path) && self::quietly(static fn(): bool => rmdir($path))
                : self::quietly(static fn(): bool => unlink($path));
            $cleared = $removed && $cleared;
        }

        return $cleared;
    }

    /**
     * Removes a kind's run entries that no worker will read again: those of
     * a host process that is gone, those older than RUN_FRESHNESS, and those
     * of this host from an earlier analysis generation. A watch or editor
     * session writes a new entry for every generation, so without the last
     * rule they would pile up for as long as the host lives.
     *
     * @param string $key The run key being written, `run-<host>-g<generation>-<hash>`.
     */
    private function dropFinishedRuns(string $kind, string $key): void
    {
        $current = [];
        $generations = preg_match('/^(run-\d+-[^-]+)-g(\d+)-/', $key, $current) === 1;
        $older = $generations ? '/^' . preg_quote($current[1], delimiter: '/') . '-g(\d+)-/' : null;
        $entries = glob($this->directory . '/' . $kind . '-' . self::RUN_PREFIX . '*');
        $liveness = is_dir('/proc');
        $stale = time() - self::RUN_FRESHNESS;
        $host = [];
        $generation = [];
        foreach ($entries === false ? [] : $entries as $entry) {
            $name = substr(basename($entry), offset: strlen($kind) + 1);
            if (preg_match('/^run-(\d+)-/', $name, $host) !== 1) {
                continue;
            }

            $gone = $liveness && !is_dir('/proc/' . $host[1]);
            $old = (int) self::quietly(static fn(): int|false => filemtime($entry)) < $stale;
            $superseded =
                $older !== null
                && preg_match($older, $name, $generation) === 1
                && (int) $generation[1] < (int) $current[2];
            if ($gone || $old || $superseded) {
                self::quietly(static fn(): bool => unlink($entry));
            }
        }
    }

    /**
     * Removes a kind's temporary files that no write will finish: those of a
     * process that is gone, and those older than RUN_FRESHNESS. A worker
     * killed between the write and the rename leaves one behind, and no entry
     * sweep matches its name.
     */
    private function dropOrphanedTemporaries(string $kind): void
    {
        $temporaries = glob($this->directory . '/' . $kind . '-*.tmp');
        $liveness = is_dir('/proc');
        $stale = time() - self::RUN_FRESHNESS;
        $writer = [];
        foreach ($temporaries === false ? [] : $temporaries as $temporary) {
            if (preg_match('/\.(\d+)\.tmp$/', $temporary, $writer) !== 1) {
                continue;
            }

            $gone = $liveness && !is_dir('/proc/' . $writer[1]);
            $old = (int) self::quietly(static fn(): int|false => filemtime($temporary)) < $stale;
            if ($gone || $old) {
                self::quietly(static fn(): bool => unlink($temporary));
            }
        }
    }

    private function file(string $kind, string $fingerprint): string
    {
        return $this->directory . '/' . $kind . '-' . $fingerprint . '-' . self::code() . '.cache';
    }

    /**
     * A hash of this package's own index code: the path, modification time
     * and size of every file next to this one. Payloads are instances of
     * these classes and the output of their parsers, so an entry written by
     * other code must never be read.
     */
    private static function code(): string
    {
        if (self::$code !== null) {
            return self::$code;
        }

        $files = glob(__DIR__ . '/*.php');
        $lines = '';
        foreach ($files === false ? [] : $files as $file) {
            $lines .= $file . '|' . (string) (int) filemtime($file) . '|' . (string) (int) filesize($file) . "\n";
        }

        return self::$code = substr(sha1($lines), offset: 0, length: 12);
    }

    /**
     * Runs a filesystem call that can race another worker. Another process
     * may create or remove the same path at any moment, and the caller
     * handles the failed result, so PHP's warning for it is dropped.
     *
     * @template T
     * @param Closure(): T $call
     * @return T
     */
    private static function quietly(Closure $call): mixed
    {
        set_error_handler(static fn(): bool => true);
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }
}
