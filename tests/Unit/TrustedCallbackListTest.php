<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\TrustedCallbackList;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TrustedCallbackListTest extends TestCase
{
    /**
     * @return iterable<string, array{string, list<string>, bool}>
     */
    public static function readable(): iterable
    {
        yield 'short array' => ["return ['preRender', 'lazyBuilder'];", ['preRender', 'lazyBuilder'], false];
        yield 'long array, trailing comma' => ["return array('a', \"b\",);", ['a', 'b'], false];
        yield 'empty' => ['return [];', [], false];
        yield 'escaped quote' => ["return ['it\\'s'];", ["it's"], false];
        yield 'grown from the parent' => [
            "\$callbacks = parent::trustedCallbacks();\n\$callbacks[] = 'a';\n\$callbacks[] = 'b';\nreturn \$callbacks;",
            ['a', 'b'],
            true,
        ];
        yield 'merged after the parent' => [
            "return array_merge(parent::trustedCallbacks(), ['a']);",
            ['a'],
            true,
        ];
        yield 'merged before the parent' => [
            "return \\array_merge(['a'], parent::trustedCallbacks());",
            ['a'],
            true,
        ];
        yield 'variable merged on return' => ["\$list = ['a'];\nreturn array_merge(\$list, ['b']);", ['a', 'b'], false];
    }

    /**
     * @param list<string> $names
     */
    #[DataProvider('readable')]
    public function testReadsTheList(string $body, array $names, bool $parent): void
    {
        $list = TrustedCallbackList::parse(self::method($body));

        self::assertNotNull($list);
        self::assertSame($names, $list->names);
        self::assertSame($parent, $list->parent);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unreadable(): iterable
    {
        yield 'constant' => ['return self::CALLBACKS;'];
        yield 'array union' => ["return ['a'] + parent::trustedCallbacks();"];
        yield 'branch' => ["if (\\Drupal::hasService('x')) {\n  return ['a'];\n}\nreturn [];"];
        yield 'string' => ["return 'a';"];
        yield 'interpolated name' => ["return [\"\$name\"];"];
        yield 'another static call' => ['return static::names();'];
        yield 'appended from a call' => ["\$callbacks = [];\n\$callbacks[] = self::name();\nreturn \$callbacks;"];
    }

    #[DataProvider('unreadable')]
    public function testGivesNoAnswerForAnyOtherBody(string $body): void
    {
        self::assertNull(TrustedCallbackList::parse(self::method($body)));
    }

    public function testSkipsTheDocblockAndAttributesBeforeTheMethod(): void
    {
        $source = "/**\n * {@inheritdoc}\n */\n#[\\Override]\npublic static function trustedCallbacks(): array {\n  return ['a'];\n}";

        self::assertSame(['a'], TrustedCallbackList::parse($source)?->names);
    }

    private static function method(string $body): string
    {
        return "public static function trustedCallbacks() {\n{$body}\n}";
    }
}
