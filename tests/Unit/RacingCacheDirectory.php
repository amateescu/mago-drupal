<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Cache\DiskCache;

use function clearstatcache;
use function posix_geteuid;
use function serialize;
use function strlen;
use function substr;

/**
 * A stream wrapper that fakes one cache directory that two workers race on.
 *
 * The directory starts open to everyone. When the first worker renames it
 * aside, the wrapper acts out what happens while that worker was paused
 * before the rename. A second worker replaces the directory, checks it and
 * reads an entry from it. The rename then moves the second worker's
 * directory away, and another user creates the path with a planted entry.
 * Every path shows the same directory, and every entry has the same
 * contents.
 *
 * @mago-expect lint:too-many-methods
 */
final class RacingCacheDirectory
{
    public const SCHEME = 'mago-drupal-race';

    private const OPEN = 0o40_777;

    private const CLOSED = 0o40_700;

    /**
     * @var resource|null
     */
    public mixed $context = null;

    public static ?DiskCache $second = null;

    /**
     * What the second worker read before the other user took the path.
     */
    public static mixed $before = null;

    public static bool $writtenAfter = false;

    /**
     * Who has the path: `first` while it is the open directory, `second`
     * while it is the second worker's, and `other` once the other user has
     * it.
     */
    private static string $stage = 'first';

    private string $data = '';

    private int $owner = 0;

    private int $position = 0;

    public static function reset(): void
    {
        self::$stage = 'first';
        self::$second = null;
        self::$before = null;
        self::$writtenAfter = false;
    }

    /**
     * The user who owns the path now.
     */
    private static function owner(): int
    {
        return self::$stage === 'other' ? posix_geteuid() + 1 : posix_geteuid();
    }

    /**
     * @return array<string, int>
     */
    public function url_stat(string $path, int $flags): array
    {
        return ['mode' => self::$stage === 'second' ? self::CLOSED : self::OPEN, 'uid' => self::owner(), 'size' => 0];
    }

    public function rename(string $from, string $to): bool
    {
        self::$stage = 'second';
        clearstatcache();
        self::$second = new DiskCache(self::SCHEME . '://cache/root', owned: self::SCHEME . '://cache');
        self::$before = self::$second->get('probe', 'a');

        self::$stage = 'other';
        clearstatcache();

        return true;
    }

    public function stream_metadata(string $path, int $option, mixed $value): bool
    {
        return true;
    }

    public function dir_opendir(string $path, int $options): bool
    {
        return false;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        return false;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$opened): bool
    {
        if ($mode !== 'r' && $mode !== 'rb') {
            self::$writtenAfter = self::$writtenAfter || self::$stage === 'other';

            return false;
        }

        $this->data = serialize([self::$stage === 'other' ? 'planted' : 'own']);
        $this->owner = self::owner();

        return true;
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return ['mode' => 0o100_600, 'uid' => $this->owner, 'size' => strlen($this->data)];
    }

    public function stream_read(int $count): string
    {
        $chunk = substr($this->data, offset: $this->position, length: $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen($this->data);
    }
}
