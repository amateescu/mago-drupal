<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Deprecation;

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
     * The characters of the keywords and the type in a declaration's header.
     */
    private const HEADER = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789_\\|&? \t\r\n";

    private function __construct() {}

    /**
     * The `@deprecated` text of the docblock that ends right before the
     * declaration at the offset, or null when there is none.
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
     * The docblock that ends right before the declaration at the offset, or
     * null when there is none.
     */
    public static function docblockAbove(string $source, int $offset): ?string
    {
        $before = rtrim(substr($source, offset: 0, length: self::headerStart($source, $offset)));
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
     * Where the header of the declaration at the offset starts: its
     * attributes, modifiers, `const` keyword and type, such as
     * `#[Attr] public ?\Foo\Bar`. A method's span starts at its header, but
     * a constant's starts at its name and a property's name location at its
     * variable. A DNF type's parentheses stop the walk, since a parameter
     * list's would take a promoted property back to its constructor.
     */
    private static function headerStart(string $source, int $offset): int
    {
        $start = $offset;
        while ($start > 0) {
            $char = $source[$start - 1];
            if ($char === ']') {
                $attribute = strrpos(substr($source, offset: 0, length: $start), needle: '#[');
                if ($attribute === false) {
                    break;
                }

                $start = $attribute;
                continue;
            }

            if (!str_contains(self::HEADER, $char)) {
                break;
            }

            $start--;
        }

        return $start;
    }

    /**
     * The text of the first `@deprecated` tag in a docblock, on one line, or
     * null when it doesn't have one.
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
