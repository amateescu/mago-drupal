<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Cache;

use Closure;

/**
 * What a scan read from each file of a list, with the modification time and
 * size each file had then, so that the next scan reads again only the files
 * that changed.
 *
 * An index built from every source file under a root is built again when
 * any one of them changes. With the earlier scan at hand, that costs a stat
 * per file and a read of the changed ones, instead of a read of all of them.
 *
 * @internal
 */
final class FileScan
{
    /**
     * @param array<string, array{string|null, mixed}> $files Path to the
     *   stamp the file had when it was read (see DiskCache::stamps()) and
     *   what was read. A file with a null stamp is read again next time.
     */
    private function __construct(
        private readonly array $files,
    ) {}

    /**
     * Reads the files that are new or changed since the earlier scan, and
     * takes the rest from it. Without an earlier scan, it reads them all.
     *
     * @param array<string, string|null> $stamps Each file's stamp now (see
     *   DiskCache::stamps()), by path in list order.
     * @param Closure(string): mixed $read What one file holds, from its path.
     * @return array{self, array<string, mixed>} The new scan, and what each
     *   file holds, by path in list order.
     */
    public static function update(?self $earlier, array $stamps, Closure $read): array
    {
        $known = $earlier === null ? [] : $earlier->files;
        $files = [];
        $held = [];
        foreach ($stamps as $path => $stamp) {
            $kept = $known[$path] ?? null;
            /** @var mixed $holds */
            $holds = $kept !== null && $stamp !== null && $kept[0] === $stamp ? $kept[1] : $read($path);
            $files[$path] = [$stamp, $holds];
            $held[$path] = $holds;
        }

        return [new self($files), $held];
    }
}
