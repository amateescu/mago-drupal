<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;

use function file_get_contents;
use function is_file;
use function preg_match;
use function preg_replace;
use function rtrim;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;

/**
 * Reads the `@deprecated` text of a declaration out of raw PHP source.
 *
 * Mago keeps only a flag for the tag, so the text has to come from the file.
 *
 * @internal
 */
final class DeprecatedTag
{
    /**
     * The tag and its continuation lines, up to the next tag or the end of
     * the docblock. The inline `{@deprecated}` form does not count.
     */
    private const TAG = '/(?<![\w{])@deprecated\b(.*?)(?=^\s*\*\s*@|\*\/|\z)/ms';

    private const NOT_DEPRECATED = '@not-deprecated';

    /**
     * A global constant's span starts at its name, after the keyword.
     */
    private const CONST_KEYWORD = '/\bconst$/i';

    private function __construct() {}

    /**
     * The `@deprecated` text of the docblock that ends right before the
     * offset, or null when there is none.
     *
     * A declaration's span starts at its attributes or modifiers, so only
     * whitespace sits between the span and its docblock.
     */
    public static function above(string $source, int $offset): ?string
    {
        $docblock = self::docblockAbove($source, $offset);

        return $docblock === null ? null : self::text($docblock);
    }

    /**
     * Whether a method's own docblock says `@not-deprecated`, which keeps it
     * from inheriting a deprecation.
     */
    public static function optsOut(FunctionLikeMetadata $method): bool
    {
        $file = $method->location->file;
        $source = $file !== null && $method->hasDocblock && is_file($file) ? file_get_contents($file) : false;
        $docblock = $source === false ? null : self::docblockAbove($source, $method->location->span->start);

        return $docblock !== null && str_contains($docblock, self::NOT_DEPRECATED);
    }

    /**
     * The docblock that ends right before the offset, or null when there is
     * none.
     */
    public static function docblockAbove(string $source, int $offset): ?string
    {
        $before = rtrim(substr($source, offset: 0, length: $offset));
        if (preg_match(self::CONST_KEYWORD, $before) === 1) {
            $before = rtrim(substr($before, offset: 0, length: -5));
        }

        $start = str_ends_with($before, '*/') ? strrpos($before, needle: '/**') : false;
        if ($start === false) {
            return null;
        }

        // A plain `/* */` comment in between would pair its end with an
        // earlier docblock's start.
        $docblock = substr($before, $start);

        return strpos($docblock, needle: '*/') === (strlen($docblock) - 2) ? $docblock : null;
    }

    /**
     * The text of the first `@deprecated` tag in a docblock, on one line, or
     * null when it has none.
     */
    public static function text(string $docblock): ?string
    {
        $matches = [];
        if (preg_match(self::TAG, $docblock, $matches) !== 1) {
            return null;
        }

        return trim((string) preg_replace('/\s*\n\s*\*?\s*/', replacement: ' ', subject: $matches[1]));
    }
}
