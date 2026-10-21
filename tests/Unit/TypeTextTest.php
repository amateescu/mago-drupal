<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\TypeText;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TypeTextTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function unions(): iterable
    {
        yield 'string' => ['string', true];
        yield 'nullable string' => ['null|string', true];
        yield 'int or string' => ['int|string', true];
        yield 'array key' => ['array-key', true];
        yield 'an array or a string' => ['array<string, int>|string', true];
        yield 'int' => ['int', false];
        yield 'non-empty string' => ['non-empty-string', false];
        yield 'string only inside an array' => ['array<string, string>', false];
        yield 'a literal string' => ["'a'|'b'", false];
        yield 'unbalanced' => ['array<string|int', false];
        yield 'a string before a literal' => ["string|string('x')", true];
        yield 'a string after a literal' => ["array<string('x>'), int>|string", true];
        yield 'a literal with a quote inside' => ["string('it's|string')", false];
        yield 'a literal that looks like a union' => ["string(')|string|(')", false];
        yield 'a literal that closes its brackets' => ["string('')|string|('')", false];
        yield 'a literal with escapes' => ["string('a\\\"|string|\\\"b')", false];
        yield 'a literal between two string types' => ["string('a')|string|string('b')", false];
        yield 'a quoted shape key' => ["array{'a|b': string}|int", false];
        yield 'a list shape with a literal' => ["list{string('('), string}", false];
        yield 'a closure that returns a string' => ['(closure(int): int|string)', false];
        yield 'a callable that returns a string' => ['(callable(int): string)|int', false];
        yield 'a class string' => ['class-string<Exception>|string', true];
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function arrays(): iterable
    {
        yield 'string keys' => ['array<string, Drupal\Core\Field\BaseFieldDefinition&static>', true];
        yield 'array-key keys' => ['array<array-key, int>', true];
        yield 'int or string keys' => ['non-empty-array<int|string, string>', true];
        yield 'nested value' => ['array<string, array<int, string>>', true];
        yield 'int keys' => ['array<int, string>', false];
        yield 'a list' => ['list<string>', false];
        yield 'an empty array' => ['array{}', false];
        yield 'a shape' => ["array{'a': int}", false];
        yield 'a union of arrays' => ['array<string, int>|array<int, int>', false];
        yield 'a nullable array' => ['array<string, int>|null', false];
        yield 'a literal value' => ["array<string, string('x')>", false];
        yield 'a literal that hides a second array' => ["array<string, string('a')>|array<int, string('b')>", false];
    }

    #[DataProvider('unions')]
    public function testHoldsStrings(string $union, bool $holds): void
    {
        self::assertSame($holds, TypeText::holdsStrings($union));
    }

    #[DataProvider('arrays')]
    public function testKeysHoldStrings(string $type, bool $holds): void
    {
        self::assertSame($holds, TypeText::keysHoldStrings($type));
    }
}
