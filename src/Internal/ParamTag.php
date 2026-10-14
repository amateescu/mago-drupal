<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function ltrim;
use function preg_match;
use function strlen;
use function strpos;
use function substr;
use function trim;

/**
 * A `@param` tag read the way Drupal.Commenting.FunctionComment reads it:
 * the type before the variable, the variable, and the description.
 *
 * @internal
 */
final class ParamTag
{
    /**
     * The type and the variable of a `@param` line. The type is everything
     * before the first `$`, `.` or `&$`. A line with no variable gives an
     * empty second group.
     */
    public const LINE = '/((?:(?![$.]|&(?=\$)).)*)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/';

    private function __construct(
        public readonly DocblockTag $tag,
        public readonly string $variable,
        public readonly string $type,
        public readonly string $rest,
        public readonly string $description,
    ) {}

    /**
     * Reads the tag, or returns null when it has no variable. The `&` of a
     * reference and the `...` of a variadic parameter belong to the
     * variable, as in Coder, so `@param &$a` has no type.
     */
    public static function read(DocblockTag $tag): ?self
    {
        $content = $tag->content();
        $matches = [];
        if (preg_match('/(?:&|\.\.\.)*\$[A-Za-z_][A-Za-z0-9_]*/', $content, $matches) !== 1) {
            return null;
        }

        $variable = $matches[0];
        $offset = strpos($content, $variable);
        $offset = $offset === false ? 0 : $offset;
        $type = trim(substr($content, offset: 0, length: $offset));
        $rest = substr($content, $offset + strlen($variable));
        $description = $type === '' ? self::untypedDescription($tag, $rest) : ltrim($rest, characters: ". \t");

        return new self($tag, $variable, $type, $rest, $description);
    }

    /**
     * The description of a `@param` with no type. Coder reads a single word
     * after the variable on the tag's line as the type. In that case the
     * description is the lines below.
     */
    private static function untypedDescription(DocblockTag $tag, string $rest): string
    {
        $matches = [];
        preg_match(self::LINE, $tag->lines[0]->text, $matches);
        $after = trim($matches[4] ?? '');
        if ($after !== '' && preg_match('/\s/', $after) !== 1) {
            return $tag->textBelow();
        }

        return ltrim($rest, characters: ". \t");
    }
}
