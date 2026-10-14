<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\ResolvedName;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

use function array_key_exists;
use function count;
use function intdiv;
use function ltrim;
use function strtolower;
use function usort;

/**
 * The resolved names of one file, decoded once and sorted by offset, with
 * the answers for the spans asked about so far.
 *
 * The SDK decodes every name of the file on each `getResolvedNames()` call,
 * and several hooks ask about the same class node, so the names are read
 * once per file and a span's names found by a binary search.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class FileNames
{
    /**
     * @var WeakMap<SourceFile, self>|null
     */
    private static ?WeakMap $files = null;

    /**
     * Answers so far, keyed by `start:end`, or the empty string for the whole
     * file.
     *
     * @var array<string, array{array<string, true>, list<non-empty-string>}>
     */
    private array $answers = [];

    /**
     * @param list<ResolvedName> $names Sorted by start offset.
     */
    private function __construct(
        private readonly array $names,
    ) {}

    public static function of(SourceFile $file): self
    {
        self::$files ??= new WeakMap();
        $files = self::$files;
        if (!$files->offsetExists($file)) {
            $names = $file->getResolvedNames();
            usort($names, static fn(ResolvedName $a, ResolvedName $b): int => $a->span->start <=> $b->span->start);
            $files->offsetSet($file, new self($names));
        }

        return $files->offsetGet($file);
    }

    /**
     * The names mentioned inside the span, lowercased and keyed, and the
     * unimported ones in source order as declaration candidates.
     *
     * @return array{array<string, true>, list<non-empty-string>}
     */
    public function within(?Span $span): array
    {
        $key = $span === null ? '' : $span->start . ':' . $span->end;

        return $this->answers[$key] ??= $this->collect($span);
    }

    /**
     * @return array{array<string, true>, list<non-empty-string>}
     */
    private function collect(?Span $span): array
    {
        $mentions = [];
        $candidates = [];
        $count = count($this->names);
        for ($i = $span === null ? 0 : $this->firstAt($span->start); $i < $count; $i++) {
            $name = $this->names[$i];
            if ($span !== null && $name->span->start >= $span->end) {
                break;
            }

            $resolved = ltrim($name->name, characters: '\\');
            if ($resolved === '' || $span !== null && !$span->contains($name->span)) {
                continue;
            }

            $key = strtolower($resolved);
            if (!$name->imported && !array_key_exists($key, $mentions)) {
                $candidates[] = $resolved;
            }

            $mentions[$key] = true;
        }

        return [$mentions, $candidates];
    }

    /**
     * The index of the first name starting at or after the offset.
     */
    private function firstAt(int $offset): int
    {
        $low = 0;
        $high = count($this->names);
        while ($low < $high) {
            $middle = intdiv(num1: $low + $high, num2: 2);
            $before = $this->names[$middle]->span->start < $offset;
            $low = $before ? $middle + 1 : $low;
            $high = $before ? $high : $middle;
        }

        return $low;
    }
}
