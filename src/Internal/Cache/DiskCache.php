<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Cache;

use Closure;
use Composer\InstalledVersions;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;

use function basename;
use function class_exists;
use function clearstatcache;
use function fclose;
use function filemtime;
use function filesize;
use function flock;
use function fopen;
use function getenv;
use function getmypid;
use function glob;
use function is_dir;
use function is_object;
use function is_string;
use function microtime;
use function preg_match;
use function preg_quote;
use function preg_replace;
use function rename;
use function serialize;
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
use const PHP_OS_FAMILY;
use const PHP_VERSION;

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
 * name also carries a hash of this package's own code, the PHP version and
 * the symfony/yaml version (see code()), so an update of any of them never
 * reads a payload an older version wrote.
 *
 * Metadata-backed indexes cannot outlive a run, but within one run every
 * worker builds the same ones from the same frozen codebase. `shared()` lets
 * the first worker build and the others load its result.
 *
 * `MAGO_DRUPAL_CACHE=0` turns the cache off; any other value is the directory
 * to use. The default sits under the system temporary directory, one per
 * user. CacheDirectory decides whether it may be read and written, and opens
 * its entries. With no way to tell the user's id, there isn't a default cache.
 * `scoped()` gives each Drupal root its own subdirectory, so the
 * one-entry-per-kind sweep never crosses roots.
 *
 * A worker registers its plugins before the SDK sends stray output to
 * stderr, and stdout is the protocol stream. Filesystem calls that race
 * another worker go through CacheDirectory::quietly(), so an expected failure
 * never prints a warning.
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
     * others, and on a system that doesn't publish process start times this is
     * what keeps a run from reading an entry left by an older one whose host
     * had the same process id.
     */
    private const RUN_FRESHNESS = 1_800;

    private const RECENT = 2;

    /**
     * The packages whose output goes into payloads. symfony/yaml parses the
     * services and config schema files.
     */
    private const PARSERS = ['symfony/yaml'];

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
     * Checks the owned directory before every read and write.
     */
    private readonly CacheDirectory $trust;

    /**
     * @param string|null $owned A directory in a shared temporary directory
     *   that must belong to this user before anything is read from or written
     *   under it (see CacheDirectory).
     */
    public function __construct(
        private readonly string $directory,
        private readonly ?string $owned = null,
    ) {
        $this->trust = new CacheDirectory($owned);
    }

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

        // Windows gives every user a temporary directory of their own, and
        // doesn't report a file owner to check.
        if (PHP_OS_FAMILY === 'Windows') {
            return new self(sys_get_temp_dir() . '/mago-drupal');
        }

        // Elsewhere the temporary directory is shared with other users, and a
        // directory there can only be trusted once its owner is known to be
        // this user.
        $user = CacheDirectory::user();
        if ($user === null) {
            return null;
        }

        $directory = sys_get_temp_dir() . '/mago-drupal-' . (string) $user;

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

        // PHP keeps the last stat result, and before 8.3 a write does not
        // drop it, so a file stat'd again after an edit would give the old
        // time and size.
        clearstatcache();

        // A file can disappear between the listing and the stat, which then
        // counts as time and size 0.
        return CacheDirectory::quietly(static function () use ($paths, $recent): ?string {
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
     * The stamp of each file (see stamp()), by path.
     *
     * @param list<string> $paths
     * @return array<string, string|null>
     */
    public static function stamps(array $paths): array
    {
        // PHP keeps the last stat result, and before 8.3 a write does not
        // drop it.
        clearstatcache();
        $stamps = [];
        foreach ($paths as $path) {
            $stamps[$path] ??= self::stamp($path);
        }

        return $stamps;
    }

    /**
     * The modification time and size of one file, as `<mtime>|<size>`, or
     * null while it may still be changing (see fingerprint()). A missing
     * file counts as time and size 0. The caller clears the stat cache.
     */
    public static function stamp(string $path): ?string
    {
        $recent = time() - self::RECENT;

        return CacheDirectory::quietly(static function () use ($path, $recent): ?string {
            $mtime = (int) filemtime($path);

            return $mtime >= $recent ? null : (string) $mtime . '|' . (string) (int) filesize($path);
        });
    }

    /**
     * @param list<class-string> $classes Classes the stored value may contain.
     */
    public function get(string $kind, string $fingerprint, array $classes = []): mixed
    {
        if (!$this->trust->trusted()) {
            return null;
        }

        // Another worker's sweep can remove the entry at any moment.
        $data = $this->trust->read($this->file($kind, $fingerprint));
        if ($data === false || $data === '') {
            return null;
        }

        /** @var mixed $value */
        $value = CacheDirectory::quietly(static fn(): mixed => unserialize($data, ['allowed_classes' => $classes]));

        return $value === false ? null : $value;
    }

    /**
     * Writes atomically and drops the same kind's entries for other
     * fingerprints, so a root's directory holds one entry per kind. Run
     * entries are dropped by dropFinishedRuns() instead.
     */
    public function set(string $kind, string $fingerprint, mixed $value): void
    {
        if (!$this->trust->prepare($this->directory)) {
            return;
        }

        $file = $this->file($kind, $fingerprint);
        if (!str_starts_with($fingerprint, self::RUN_PREFIX)) {
            $stale = glob(self::literal($this->directory . '/' . $kind) . '-*.cache');
            foreach ($stale === false ? [] : $stale as $entry) {
                // The rename below replaces the entry for this key in one
                // step, so a reader never finds it missing.
                if ($entry === $file || str_contains(basename($entry), '-' . self::RUN_PREFIX)) {
                    continue;
                }

                CacheDirectory::quietly(static fn(): bool => unlink($entry));
            }
        }

        $this->dropOrphanedTemporaries($kind);
        $temporary = $file . '.' . (string) getmypid() . '.tmp';
        if (!CacheDirectory::create($temporary, serialize($value))) {
            // A full disk leaves part of the file behind. A file that was
            // already at the path, such as one left by a dead worker with the
            // same process id, stops the write. Either one is removed here.
            CacheDirectory::quietly(static fn(): bool => unlink($temporary));

            return;
        }

        if (!CacheDirectory::quietly(static fn(): bool => rename($temporary, $file))) {
            CacheDirectory::quietly(static fn(): bool => unlink($temporary));
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

        if (!$this->trust->prepare($this->directory)) {
            return $build();
        }

        $path = $this->file($kind, $key) . '.lock';
        $lock = CacheDirectory::quietly(static fn() => fopen($path, mode: 'c'));
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
        $mtime = CacheDirectory::quietly(static fn(): int|false => filemtime($file));
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
     * Removes a kind's run entries that won't be read again: those of
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
        $entries = glob(self::literal($this->directory . '/' . $kind) . '-' . self::RUN_PREFIX . '*');
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
            $old = (int) CacheDirectory::quietly(static fn(): int|false => filemtime($entry)) < $stale;
            $superseded = false;
            if ($older !== null && preg_match($older, $name, $generation) === 1) {
                $superseded = (int) $generation[1] < (int) $current[2];
            }
            if ($gone || $old || $superseded) {
                CacheDirectory::quietly(static fn(): bool => unlink($entry));
            }
        }
    }

    /**
     * Removes a kind's temporary files whose write won't finish: those of a
     * process that is gone, and those older than RUN_FRESHNESS. A worker
     * killed between the write and the rename leaves one behind, and no entry
     * sweep matches its name.
     */
    private function dropOrphanedTemporaries(string $kind): void
    {
        $temporaries = glob(self::literal($this->directory . '/' . $kind) . '-*.tmp');
        $liveness = is_dir('/proc');
        $stale = time() - self::RUN_FRESHNESS;
        $writer = [];
        foreach ($temporaries === false ? [] : $temporaries as $temporary) {
            if (preg_match('/\.(\d+)\.tmp$/', $temporary, $writer) !== 1) {
                continue;
            }

            $gone = $liveness && !is_dir('/proc/' . $writer[1]);
            $old = (int) CacheDirectory::quietly(static fn(): int|false => filemtime($temporary)) < $stale;
            if ($gone || $old) {
                CacheDirectory::quietly(static fn(): bool => unlink($temporary));
            }
        }
    }

    /**
     * The text as a glob pattern that matches only itself. A kind can hold a
     * class name, and glob() reads its backslashes as escapes.
     */
    private static function literal(string $text): string
    {
        return (string) preg_replace('/[\\\\*?\[\]]/', replacement: '\\\\$0', subject: $text);
    }

    private function file(string $kind, string $fingerprint): string
    {
        return $this->directory . '/' . $kind . '-' . $fingerprint . '-' . self::code() . '.cache';
    }

    /**
     * The PHP version and the installed version of each package in PARSERS,
     * one per line. PHP's tokenizer reads the PHP files, so the PHP version
     * counts as one. A package that isn't installed, or a run without
     * Composer's autoloader, leaves its version empty.
     */
    public static function parserVersions(): string
    {
        $lines = 'php|' . PHP_VERSION . "\n";
        $composer = class_exists(InstalledVersions::class);
        foreach (self::PARSERS as $package) {
            $installed = $composer && InstalledVersions::isInstalled($package);
            $version = $installed
                ? (string) InstalledVersions::getVersion($package)
                . '|'
                . (string) InstalledVersions::getReference($package)
                : '';
            $lines .= $package . '|' . $version . "\n";
        }

        return $lines;
    }

    /**
     * A hash of the code that builds the payloads: the path, modification
     * time and size of every file next to this one, and the parser versions
     * (see parserVersions()). Payloads are instances of these classes and the
     * output of their parsers, so an entry written by other code must never
     * be read.
     */
    private static function code(): string
    {
        if (self::$code !== null) {
            return self::$code;
        }

        $files = glob(__DIR__ . '/*.php');
        $lines = self::parserVersions();
        foreach ($files === false ? [] : $files as $file) {
            $lines .= $file . '|' . (string) (int) filemtime($file) . '|' . (string) (int) filesize($file) . "\n";
        }

        return self::$code = substr(sha1($lines), offset: 0, length: 12);
    }
}
