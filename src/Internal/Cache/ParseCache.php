<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Cache;

use Closure;

use function array_filter;
use function implode;
use function sha1;

/**
 * Loads parsed forms of a list of files through the disk cache, keyed by the
 * files' modification times and sizes.
 *
 * Several indexes read the same file lists, so the fingerprint of each list
 * and the stamp of each file are taken once and kept for the life of this
 * object. Without a disk cache every load parses.
 *
 * @internal
 */
final class ParseCache
{
    /**
     * Fingerprints of the file lists read so far, by a hash of the list.
     *
     * @var array<string, string>
     */
    private array $fingerprints = [];

    /**
     * The stamps of the files read so far, by path. A file still being
     * written doesn't have a stamp and isn't kept.
     *
     * @var array<string, string>
     */
    private array $stamps = [];

    public function __construct(
        private readonly ?DiskCache $cache,
    ) {}

    /**
     * Loads a parsed form of the files through the cache, keyed by their
     * current modification times and sizes.
     *
     * @template T
     * @param list<string> $paths
     * @param Closure(): T $parse
     * @param list<class-string> $classes Classes the parsed value may contain.
     * @return T
     */
    public function cached(string $kind, array $paths, Closure $parse, array $classes = []): mixed
    {
        $cache = $this->cache;
        if ($cache === null) {
            return $parse();
        }

        // Several indexes read the same source file list, so each list's
        // files are checked once per worker. A list with a file still being
        // written doesn't have a fingerprint, and is checked again next time.
        $list = sha1(implode("\0", $paths));
        $fingerprint = $this->fingerprints[$list] ?? $this->fingerprint($paths);
        if ($fingerprint !== null) {
            $this->fingerprints[$list] = $fingerprint;
        }

        if ($fingerprint === null) {
            // A file is still being written. An entry keyed on it now would
            // never be read again.
            return $parse();
        }

        /** @var T|null $cached */
        $cached = $cache->get($kind, $fingerprint, $classes);
        if ($cached !== null) {
            return $cached;
        }

        $parsed = $parse();
        $cache->set($kind, $fingerprint, $parsed);

        return $parsed;
    }

    /**
     * Loads an index built from what each file holds through the cache, the
     * way cached() does. After a file changed, it reads only the changed
     * files again: what each file held at the last build is kept in a
     * `scan-<kind>` entry (see FileScan).
     *
     * @template T
     * @param list<string> $paths
     * @param Closure(string): mixed $read What one file holds, from its path.
     * @param Closure(array<string, mixed>): T $build The index, from what
     *   each file holds, by path in the order of `$paths`.
     * @param list<class-string> $classes Classes the index, or what one file
     *   holds, may contain.
     * @return T
     */
    public function scanned(string $kind, array $paths, Closure $read, Closure $build, array $classes = []): mixed
    {
        $cache = $this->cache;

        return $this->cached(
            $kind,
            $paths,
            function () use ($cache, $kind, $paths, $read, $build, $classes): mixed {
                /** @var mixed $earlier */
                $earlier = $cache?->get('scan-' . $kind, 'root', [FileScan::class, ...$classes]);
                [$scan, $holds] = FileScan::update(
                    $earlier instanceof FileScan ? $earlier : null,
                    $this->stamps($paths),
                    $read,
                );
                $cache?->set('scan-' . $kind, 'root', $scan);

                return $build($holds);
            },
            $classes,
        );
    }

    /**
     * The stamps of the files (see DiskCache::stamps()). Each file is stat'd
     * once, since several indexes read the same files in different lists.
     *
     * @param list<string> $paths
     * @return array<string, string|null>
     */
    public function stamps(array $paths): array
    {
        $missing = [];
        foreach ($paths as $path) {
            if (($this->stamps[$path] ?? null) !== null) {
                continue;
            }

            $missing[] = $path;
        }

        $taken = $missing === [] ? [] : DiskCache::stamps($missing);
        $this->stamps = [...$this->stamps, ...array_filter($taken, static fn(?string $stamp): bool => $stamp !== null)];
        $stamps = [];
        foreach ($paths as $path) {
            $stamps[$path] = $this->stamps[$path] ?? null;
        }

        return $stamps;
    }

    /**
     * The list's fingerprint from its stamps, the same hash as
     * DiskCache::fingerprint() gives, or null while a file is still being
     * written.
     *
     * @param list<string> $paths
     */
    private function fingerprint(array $paths): ?string
    {
        $lines = '';
        foreach ($this->stamps($paths) as $path => $stamp) {
            if ($stamp === null) {
                return null;
            }

            $lines .= $path . '|' . $stamp . "\n";
        }

        return sha1($lines);
    }
}
