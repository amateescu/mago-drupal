<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use function in_array;
use function preg_match;
use function str_ends_with;
use function strlen;
use function strpos;
use function strspn;
use function substr;

/**
 * Reads the names that a docblock tag gives to classes, with their offsets in
 * the tag's content.
 *
 * Each tag shape has its own reader. A type tag, such as `@param` or
 * `@property`, starts with the type. A `@phpstan-type` or `@psalm-type` tag
 * names the alias first. A `@see` tag names a class or a member, as in
 * `Foo::bar()`, in its first word. A `@method` tag has an optional return
 * type and the types of its parameters. Keywords such as `int` come out as
 * names too, which don't match any import.
 *
 * @internal
 */
final class TagClassNames
{
    /**
     * The tags whose content starts with a type.
     */
    private const TYPE_TAGS = [
        'param',
        'return',
        'var',
        'throws',
        'mixin',
        'property',
        'property-read',
        'property-write',
        'phpstan-param',
        'phpstan-return',
        'phpstan-var',
        'psalm-param',
        'psalm-return',
        'psalm-var',
    ];

    /**
     * A name at the start of the text, such as a type alias or a method.
     */
    private const NAME = '[A-Za-z_\x80-\xff][\w\x80-\xff]*';

    /**
     * The first word of a `@see` tag when it names a class, a constant, a
     * property or a method of one: `Foo`, `Foo::BAR`, `Foo::$bar` or
     * `Foo::bar()`. A function, a file or a URL doesn't match.
     */
    private const SEE =
        '/^(\\\\?' . self::NAME . '(?:\\\\' . self::NAME . ')*)(?:::\$?' . self::NAME . '(?:\(\))?)?(?=\s|$)/';

    private function __construct() {}

    /**
     * The names in the tag's content, each with its offset, and the length
     * of the part of the content they come from, or null for a tag that
     * doesn't name a class this way.
     *
     * @return ?array{int, list<array{string, int}>}
     */
    public static function of(string $tag, string $content): ?array
    {
        if (in_array($tag, self::TYPE_TAGS, strict: true)) {
            return self::type($content, offset: 0);
        }

        return match ($tag) {
            'phpstan-type', 'psalm-type' => self::alias($content),
            'see' => self::see($content),
            'method' => self::method($content),
            default => null,
        };
    }

    /**
     * The names of the type that starts the text, which starts at $offset
     * in the tag's content.
     *
     * @return ?array{int, list<array{string, int}>}
     */
    private static function type(string $text, int $offset): ?array
    {
        $type = DocType::leading($text);
        if ($type === null) {
            return null;
        }

        $names = [];
        foreach (DocType::names($type) as [$name, $position]) {
            $names[] = [$name, $offset + $position];
        }

        return [$offset + strlen($type), $names];
    }

    /**
     * The names of the type after the alias, in `Alias = Type` or
     * `Alias Type`.
     *
     * @return ?array{int, list<array{string, int}>}
     */
    private static function alias(string $content): ?array
    {
        $alias = [];
        if (preg_match('/^' . self::NAME . '\s*(?:=\s*)?/', $content, $alias) !== 1) {
            return null;
        }

        return self::type(substr($content, strlen($alias[0])), strlen($alias[0]));
    }

    /**
     * The class in the first word.
     *
     * @return ?array{int, list<array{string, int}>}
     */
    private static function see(string $content): ?array
    {
        $target = [];
        if (preg_match(self::SEE, $content, $target) !== 1) {
            return null;
        }

        return [strlen($target[0]), [[$target[1], 0]]];
    }

    /**
     * The return type and the parameter types of a method, in
     * `[static] [Type] name(Type $a, ...)`.
     *
     * @return ?array{int, list<array{string, int}>}
     */
    private static function method(string $content): ?array
    {
        $static = [];
        $offset = preg_match('/^static\s+/', $content, $static) === 1 ? strlen($static[0]) : 0;
        $first = DocType::leading(substr($content, $offset));
        if ($first === null) {
            return null;
        }

        $names = [];
        if (preg_match('/^' . self::NAME . '\(/', $first) !== 1) {
            [$end, $names] = self::type($first, $offset) ?? [$offset, []];
            $offset = $end + strspn($content, characters: " \t", offset: $end);
        }

        $signature = DocType::leading(substr($content, $offset)) ?? '';
        $open = strpos($signature, needle: '(');
        if ($open === false || !str_ends_with($signature, ')')) {
            return $names === [] ? null : [$offset, $names];
        }

        $parameters = substr($signature, $open + 1, length: strlen($signature) - $open - 2);
        foreach (DocType::listItems($parameters) as [$parameter, $position]) {
            $blank = strspn($parameter, characters: " \t");
            [, $types] = self::type(substr($parameter, $blank), $offset + $open + 1 + $position + $blank) ?? [0, []];
            $names = [...$names, ...$types];
        }

        return [$offset + strlen($signature), $names];
    }
}
