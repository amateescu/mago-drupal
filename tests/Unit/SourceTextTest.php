<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\SourceText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SourceTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, ?int}>
     */
    public static function strings(): iterable
    {
        yield 'single-quoted' => ["'abc' . \$x", 5];
        yield 'escaped single quote' => ["'a\\'b' . \$x", 6];
        yield 'double-quoted' => ['"abc" . $x', 5];
        yield 'escaped double quote' => ['"a\\"b" . $x', 6];
        yield 'escaped dollar' => ['"a \\$b" . $x', 7];
        yield 'dollar before a space' => ['"a $ b" . $x', 7];
        yield 'brace without a dollar' => ['"a {b}" . $x', 7];
        yield 'variable' => ['"a $b" . $x', null];
        yield 'braced variable' => ['"a {$b}" . $x', null];
        yield 'dollar brace' => ['"a ${b}" . $x', null];
        yield 'not a string' => ["x . 'a'", null];
        yield 'unterminated' => ["'abc", null];
    }

    #[DataProvider('strings')]
    public function testFindsTheEndOfAConstantString(string $contents, ?int $end): void
    {
        self::assertSame($end, SourceText::constantStringEnd($contents, 0));
    }

    public function testSkipsWhitespaceAndComments(): void
    {
        $contents = "x /* a */ // b\n  # c\n  . 'd'";

        self::assertSame(23, SourceText::skipBlank($contents, 1));
        self::assertSame(14, SourceText::skipLineBlank($contents, 1));
    }

    public function testReadsAnAttributeAsCode(): void
    {
        self::assertNull(SourceText::commentEnd('#[Attribute]', 0));
        self::assertSame(3, SourceText::commentEnd("# a\n", 0));
    }
}
