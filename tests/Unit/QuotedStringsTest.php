<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\QuotedStrings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QuotedStringsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function literals(): iterable
    {
        yield 'empty single-quoted' => ["''", ''];
        yield 'empty double-quoted' => ['""', ''];
        yield 'lone double quote' => ["'\"'", '"'];
        yield 'lone single quote' => ["\"'\"", "'"];
        yield 'escaped single quote' => ["'\\''", "'"];
        yield 'escaped double quote' => ['"\\""', '"'];
        yield 'escaped backslash' => ["'\\\\'", '\\'];
        yield 'single-quoted newline escape' => ["'\\n'", '\\n'];
        yield 'double-quoted control escapes' => ['"\\n\\r\\t\\v\\e\\f"', "\n\r\t\v\e\f"];
        yield 'escaped dollar' => ['"\\$5"', '$5'];
        yield 'unknown escape' => ['"\\Drupal\\Core"', '\\Drupal\\Core'];
        yield 'octal' => ['"\\101\\0"', "A\0"];
        yield 'hex' => ['"\\x41\\xZ"', 'A\\xZ'];
        yield 'unicode' => ['"\\u{263A}\\u41"', "\u{263A}\\u41"];
        yield 'escaped backslash before a letter' => ['"\\\\n"', '\\n'];
        yield 'binary prefix' => ["b'x'", 'x'];
    }

    #[DataProvider('literals')]
    public function testReadsTheValueThatPhpReads(string $text, string $value): void
    {
        self::assertSame($value, QuotedStrings::value($text));
    }
}
