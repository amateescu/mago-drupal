<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\SourceText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function preg_match;

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

    public function testWalksBackOverBlanks(): void
    {
        $contents = "x;\n \t\r\n  y";

        self::assertSame(1, SourceText::lastNonBlank($contents, 9));
        self::assertSame(-1, SourceText::lastNonBlank(" \n", 2));
    }

    public function testFindsTheStartOfALine(): void
    {
        $contents = "ab\n\ncd";

        self::assertSame(0, SourceText::lineStart($contents, 0));
        self::assertSame(0, SourceText::lineStart($contents, 2));
        self::assertSame(3, SourceText::lineStart($contents, 3));
        self::assertSame(4, SourceText::lineStart($contents, 5));
    }

    public function testReadsAnAttributeAsCode(): void
    {
        self::assertNull(SourceText::commentEnd('#[Attribute]', 0));
        self::assertSame(3, SourceText::commentEnd("# a\n", 0));
    }

    public function testEndsALineCommentAtACloseTag(): void
    {
        self::assertSame(5, SourceText::commentEnd('// a ?> b', 0));
        self::assertSame(6, SourceText::commentEnd("# a ? \n", 0));
        self::assertSame(5, SourceText::skipBlank('x # a?>', 1));
    }

    /**
     * Each case is code between a callee and its parenthesis, and whether the
     * gap takes it all.
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function gaps(): iterable
    {
        yield 'nothing' => ['', true];
        yield 'whitespace' => [" \t\r\n", true];
        yield 'block comment' => ['/* a ( */', true];
        yield 'docblock' => ["/**\n * a\n */", true];
        yield 'slash comment' => ["// a (\n", true];
        yield 'hash comment' => ["# a (\n", true];
        yield 'all three kinds' => ["/* a */ // b\n # c\n", true];
        yield 'close tag in a slash comment' => ["// a ?>\n", false];
        yield 'close tag in a hash comment' => ["# a ?>\n", false];
        yield 'question mark in a comment' => ["// a?\n", true];
        yield 'attribute' => ['#[A]', false];
        yield 'code' => ['x', false];
    }

    #[DataProvider('gaps')]
    public function testMatchesTheGapBetweenTwoTokens(string $gap, bool $matches): void
    {
        self::assertSame($matches, preg_match('/\Gf' . SourceText::GAP . '\(/', 'f' . $gap . '(') === 1);
    }
}
