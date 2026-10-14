<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;

/**
 * Finds where a docblock's short description is, the way
 * Drupal.Commenting.DocComment reads it. A file docblock has its short
 * description after the `@file` tag, or on the `@file` line.
 *
 * @internal
 */
final class DocblockSummary
{
    private function __construct() {}

    /**
     * The line that holds the short description, or null when the docblock
     * has none or writes it on the `@file` line.
     *
     * @param list<DocblockRow> $rows
     */
    public static function summaryRow(array $rows): ?int
    {
        $first = DocblockRows::nextContent($rows, -1);
        if ($first === null) {
            return null;
        }

        $isFile = $rows[$first]->tag() === '@file';
        if ($isFile && $rows[$first]->value() !== '') {
            return null;
        }

        $short = $isFile ? DocblockRows::nextContent($rows, $first) : $first;

        return $short !== null && $rows[$short]->isProse() ? $short : null;
    }

    /**
     * The first line with text and the line of the short description, or
     * null when the docblock does not start with a description, after
     * `@file` or not. A description written on the `@file` line is on the
     * first line.
     *
     * @param list<DocblockRow> $rows
     * @return null|array{int, int}
     */
    public static function start(array $rows): ?array
    {
        $first = DocblockRows::nextContent($rows, -1);
        if ($first === null) {
            return null;
        }

        if ($rows[$first]->tag() === '@file' && $rows[$first]->value() !== '') {
            return [$first, $first];
        }

        $short = self::summaryRow($rows);

        return $short === null ? null : [$first, $short];
    }

    /**
     * The last line of the short description, which runs on to the first
     * line that is not prose.
     *
     * @param list<DocblockRow> $rows
     */
    public static function shortEnd(array $rows, int $short): int
    {
        $shortEnd = $short;
        while (array_key_exists($shortEnd + 1, $rows) && $rows[$shortEnd + 1]->isProse()) {
            $shortEnd++;
        }

        return $shortEnd;
    }

    /**
     * The tag that Coder reads in a docblock with no short description: the
     * first one, or the one after a leading `@file`. Null for a docblock
     * with `@file` alone. The `@file` is matched as written.
     *
     * @param non-empty-list<DocblockTag> $tags
     */
    public static function leadTag(SourceFile $file, array $tags): ?DocblockTag
    {
        if ($file->getText($tags[0]->nameSpan) !== '@file') {
            return $tags[0];
        }

        return $tags[1] ?? null;
    }
}
