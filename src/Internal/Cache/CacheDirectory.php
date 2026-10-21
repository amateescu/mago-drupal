<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Cache;

use Closure;

use function chmod;
use function clearstatcache;
use function fclose;
use function fileowner;
use function fileperms;
use function fopen;
use function fstat;
use function function_exists;
use function fwrite;
use function getmypid;
use function is_dir;
use function is_link;
use function mkdir;
use function posix_geteuid;
use function rename;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function stream_get_contents;
use function strlen;
use function sys_get_temp_dir;
use function tempnam;
use function umask;
use function uniqid;
use function unlink;

/**
 * Decides whether the disk cache may read and write its directory, and opens
 * its files safely.
 *
 * The default cache sits in the shared temporary directory, where another
 * local user could create the directory first and plant entries or links in
 * it. That directory is the owned one. It is used only while it is a real
 * directory that belongs to this user and that others can't write to (see
 * trusted()). Every entry read from it must belong to this user too (see
 * read()), and every write checks the directory again (see prepare()). A
 * cache with no owned directory, such as one the environment points at, is
 * always trusted.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 */
final class CacheDirectory
{
    /**
     * The effective user id, once user() has found it.
     */
    private static ?int $user = null;

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
        private readonly ?string $owned,
    ) {}

    /**
     * The effective user id. Without the posix extension it is the owner of
     * a file this process creates. Null when neither works.
     */
    public static function user(): ?int
    {
        if (self::$user !== null) {
            return self::$user;
        }

        if (function_exists('posix_geteuid')) {
            return self::$user = posix_geteuid();
        }

        $probe = self::quietly(static fn(): string|false => tempnam(sys_get_temp_dir(), prefix: 'mago-drupal-'));
        if ($probe === false) {
            return null;
        }

        $owner = self::quietly(static fn(): int|false => fileowner($probe));
        self::quietly(static fn(): bool => unlink($probe));

        return $owner === false ? null : (self::$user = $owner);
    }

    /**
     * The contents of an entry, or false when it can't be read or isn't
     * this user's.
     *
     * trusted() checks the owned directory once, but the path can lead
     * somewhere else later. Another worker that saw the directory open can
     * move the checked one aside (see reclaim()), and another user can then
     * create the path. So each entry is checked on its open handle, and the
     * file checked is the file read. Another user can't create a file that
     * belongs to this user.
     */
    public function read(string $file): string|false
    {
        $handle = self::quietly(static fn() => fopen($file, mode: 'r'));
        if ($handle === false) {
            return false;
        }

        try {
            if ($this->owned !== null && !self::own(self::quietly(static fn(): array|false => fstat($handle)))) {
                // The next read or write checks the directory again.
                $this->trusted = false;

                return false;
            }

            return self::quietly(static fn(): string|false => stream_get_contents($handle));
        } finally {
            fclose($handle);
        }
    }

    /**
     * Whether a file's stat shows that it belongs to this user and that
     * others can't write to it.
     *
     * @param array<array-key, int>|false $stat
     */
    private static function own(array|false $stat): bool
    {
        return $stat !== false && ($stat['uid'] ?? null) === self::user() && (($stat['mode'] ?? 0o022) & 0o022) === 0;
    }

    /**
     * Writes a new file that only this user can read or write. It fails when
     * anything is at the path already, a link included, so a planted link
     * can't send the write to another file.
     */
    public static function create(string $path, string $data): bool
    {
        $mask = umask(mask: 0o077);
        try {
            $handle = self::quietly(static fn() => fopen($path, mode: 'x'));
        } finally {
            umask($mask);
        }

        if ($handle === false) {
            return false;
        }

        try {
            return self::quietly(static fn(): int|false => fwrite($handle, $data)) === strlen($data);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Creates the cache directory, readable by this user only.
     *
     * The owned directory is created and checked before anything under it,
     * since replacing it drops what is under it (see trusted()). Another
     * worker may create either directory first; the second checks cover that.
     *
     * Every write checks the owned directory again instead of relying on an
     * earlier check, since the path may lead to another directory by now
     * (see read()). Writes are rare enough for the extra stat calls.
     */
    public function prepare(string $directory): bool
    {
        $owned = $this->owned;
        if ($owned !== null && !is_dir($owned)) {
            self::quietly(static fn(): bool => mkdir($owned, permissions: 0o700, recursive: true));
        }

        $this->trusted = false;
        if (!$this->trusted()) {
            return false;
        }

        return (
            is_dir($directory)
            || self::quietly(static fn(): bool => mkdir($directory, permissions: 0o700, recursive: true))
            || is_dir($directory)
        );
    }

    /**
     * Whether entries may be read and written. The owned directory is used
     * only while it is a real directory that belongs to this user and that
     * others can't write to. One that others can write to is replaced first
     * (see reclaim()).
     */
    public function trusted(): bool
    {
        if ($this->trusted || $this->owned === null) {
            return true;
        }

        $owned = $this->owned;
        $user = self::user();
        if ($user === null) {
            return false;
        }

        // PHP keeps the last stat result, which may be from an earlier check
        // of the same path.
        clearstatcache();
        $closed = self::closed($owned, $user);
        if ($closed === false && self::reclaim($owned)) {
            $closed = self::closed($owned, $user);
        }

        return $this->trusted = $closed === true;
    }

    /**
     * Whether a directory of this user's is closed to everyone else: true
     * when it is, false when others can write to it, and null when it isn't
     * a real directory of this user's.
     */
    private static function closed(string $directory, int $user): ?bool
    {
        if (
            is_link($directory)
            || !is_dir($directory)
            || self::quietly(static fn(): int|false => fileowner($directory)) !== $user
        ) {
            return null;
        }

        return ((int) self::quietly(static fn(): int|false => fileperms($directory)) & 0o022) === 0;
    }

    /**
     * Replaces an open directory of this user's with a new, closed one.
     *
     * Anything in the open directory may have been planted. The rename takes
     * the directory off its path in one step, so another worker never finds
     * a closed directory at the path with planted entries still in it. The
     * moved directory is then closed and emptied. Links are removed, never
     * followed. An entry that can't be deleted, such as another user's
     * subdirectory, leaves the moved directory behind.
     *
     * Another worker may be replacing it at the same time, and may move the
     * new directory aside after this one makes it. Deleting an entry it
     * wrote only costs a cache miss. The caller checks the new directory
     * again, since anyone could create the path between the rename and the
     * mkdir(). A worker that checked the directory before it was moved
     * doesn't notice the move, so it checks each entry it reads (see
     * read()) and the directory before each write (see prepare()).
     */
    private static function reclaim(string $directory): bool
    {
        $aside = $directory . '.reclaimed-' . (string) getmypid() . '-' . uniqid();
        if (!self::quietly(static fn(): bool => rename($directory, $aside))) {
            return false;
        }

        if (self::quietly(static fn(): bool => chmod($aside, permissions: 0o700)) && self::clear($aside)) {
            self::quietly(static fn(): bool => rmdir($aside));
        }

        self::quietly(static fn(): bool => mkdir($directory, permissions: 0o700));

        return true;
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
     * Runs a filesystem call that can race another worker. Another process
     * may create or remove the same path at any moment, and the caller
     * handles the failed result, so PHP's warning for it is dropped.
     *
     * @template T
     * @param Closure(): T $call
     * @return T
     */
    public static function quietly(Closure $call): mixed
    {
        set_error_handler(static fn(): bool => true);
        try {
            return $call();
        } finally {
            restore_error_handler();
        }
    }
}
