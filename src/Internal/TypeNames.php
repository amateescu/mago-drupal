<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;

use function array_unique;
use function array_values;
use function explode;
use function implode;
use function preg_match;
use function preg_replace;
use function strlen;

/**
 * Checks a `@param`, `@return` or `@var` type against the type names Coder
 * wants written another way, such as `int` for `integer`.
 *
 * Coder checks each member of a `|` union. It also strips every character
 * outside a fixed set from the type. That part is not ported, because valid
 * types use some of those characters, such as the `.` of a float literal and
 * the `=` of an optional callable parameter.
 *
 * @internal
 */
final class TypeNames
{
    /**
     * Coder's names for a type, keyed by the name written in the tag.
     */
    private const NAMES = [
        'Array' => 'array',
        'array()' => 'array',
        '[]' => 'array',
        'boolean' => 'bool',
        'Boolean' => 'bool',
        'integer' => 'int',
        'str' => 'string',
        'number' => 'int',
        'String' => 'string',
        'type' => 'mixed',
        'NULL' => 'null',
        'FALSE' => 'false',
        'TRUE' => 'true',
        'Bool' => 'bool',
        'Int' => 'int',
        'Integer' => 'int',
        'TRUEFALSE' => 'bool',
    ];

    private function __construct() {}

    /**
     * Reports a `@var` type that Coder wants written another way.
     */
    public static function checkVar(LintContext $context, DocblockTag $tag, string $type): void
    {
        self::report($context, $tag, $type, self::names($type));
    }

    /**
     * Reports a `@param` type that Coder wants written another way. Coder
     * reads the type without the `...` of a variadic parameter, and skips a
     * type with a space in it.
     */
    public static function checkParam(LintContext $context, DocblockTag $tag, string $type): void
    {
        $type = (string) preg_replace('/\s+\.{3}$/', replacement: '', subject: $type);
        if ($type !== '' && preg_match('/\s/', $type) !== 1) {
            self::report($context, $tag, $type, self::names($type));
        }
    }

    /**
     * Reports a `@return` type that Coder wants written another way. Coder
     * also drops a member that repeats, except in a generic or an array
     * shape.
     */
    public static function checkReturn(LintContext $context, DocblockTag $tag, string $type): void
    {
        $names = self::names($type);
        self::report(
            $context,
            $tag,
            $type,
            preg_match('/[<\[{(]/', $type) === 1 ? $names : array_values(array_unique($names)),
        );
    }

    /**
     * The members of the type's union, each written Coder's way.
     *
     * @return list<string>
     */
    private static function names(string $type): array
    {
        $names = [];
        foreach (explode('|', $type) as $member) {
            $names[] = self::NAMES[$member] ?? $member;
        }

        return $names;
    }

    /**
     * Reports the type when Coder writes it another way. The fix writes
     * Coder's type, when the type starts the tag's line.
     *
     * @param list<string> $names
     */
    private static function report(LintContext $context, DocblockTag $tag, string $type, array $names): void
    {
        $suggested = implode('|', $names);
        if ($suggested === $type) {
            return;
        }

        $message = "Write the @{$tag->name} type as \"{$suggested}\", not \"{$type}\".";
        $start = $tag->typeStart($type);
        if ($start === null) {
            $context->report(Issue::new($message, $tag->contentSpan()));

            return;
        }

        $span = new Span($start, $start + strlen($type));
        $context->report(Issue::new($message, $span)->withEdit(TextEdit::replace($span, $suggested)));
    }
}
