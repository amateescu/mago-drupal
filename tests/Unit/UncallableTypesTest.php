<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\UncallableTypes;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\ArrayKey;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListElement;
use Mago\Sdk\Analyzer\Type\ListType;
use PHPUnit\Framework\TestCase;

use function is_int;

/**
 * Scalars and arrays need no codebase request, so the codebase here has no
 * host behind it.
 */
final class UncallableTypesTest extends TestCase
{
    public function testScalarsAndNullCanNeverBeCalled(): void
    {
        self::assertTrue(self::uncallable(Type::int()));
        self::assertTrue(self::uncallable(Type::literalInt(123)));
        self::assertTrue(self::uncallable(Type::float()));
        self::assertTrue(self::uncallable(Type::bool()));
        self::assertTrue(self::uncallable(Type::false()));
        self::assertTrue(self::uncallable(Type::null()));
        self::assertTrue(self::uncallable(Type::union(Type::int(), Type::null())));
    }

    public function testStringsAndMixedMayBeCalled(): void
    {
        self::assertFalse(self::uncallable(Type::string()));
        self::assertFalse(self::uncallable(Type::literalString('Drupal\example\Helper::build')));
        self::assertFalse(self::uncallable(Type::mixed()));
        self::assertFalse(self::uncallable(Type::union(Type::int(), Type::string())));
        self::assertFalse(self::uncallable(Type::never()));
    }

    public function testOnlyAnArrayThatMayBeAPairMayBeCalled(): void
    {
        // array{0: string, 1: string} and array<int, string>.
        self::assertFalse(self::uncallable(self::keyed([0, 1])));
        self::assertFalse(self::uncallable(Type::array(Type::int(), Type::string())));
        // array{}, array{0, 1, 2} and array{class, method}.
        self::assertTrue(self::uncallable(Type::fromAtomic(new KeyedArrayType(null, null, null, nonEmpty: false))));
        self::assertTrue(self::uncallable(self::keyed([0, 1, 2])));
        self::assertTrue(self::uncallable(self::keyed(['class', 'method'])));
        // array{0: string, 1?: string} may hold both keys.
        self::assertFalse(self::uncallable(self::keyed([0, 1], optional: 1)));
    }

    public function testOnlyAListThatMayHoldTwoElementsMayBeCalled(): void
    {
        self::assertFalse(self::uncallable(Type::list(Type::string())));
        self::assertFalse(self::uncallable(self::sealedList(2)));
        self::assertFalse(self::uncallable(Type::fromAtomic(new ListType(Type::string(), null, 2, nonEmpty: true))));
        self::assertTrue(self::uncallable(Type::fromAtomic(new ListType(Type::string(), null, 3, nonEmpty: true))));
        self::assertTrue(self::uncallable(self::sealedList(3)));
        self::assertTrue(self::uncallable(self::sealedList(1)));
        self::assertTrue(self::uncallable(self::sealedList(0)));
    }

    private static function uncallable(Type $type): bool
    {
        return UncallableTypes::never(AnalysisGenerationTest::codebase(1), $type);
    }

    /**
     * A sealed keyed array of strings with the given keys.
     *
     * @param list<int|string> $keys
     */
    private static function keyed(array $keys, ?int $optional = null): Type
    {
        $items = [];
        foreach ($keys as $key) {
            $kind = is_int($key) ? ArrayKeyKind::Integer : ArrayKeyKind::String;
            $items[] = new ArrayItem(new ArrayKey($kind, $key), $key === $optional, Type::string());
        }

        return Type::fromAtomic(new KeyedArrayType($items, null, null, nonEmpty: true));
    }

    /**
     * A sealed list of the given number of strings.
     */
    private static function sealedList(int $count): Type
    {
        $elements = [];
        for ($index = 0; $index < $count; $index++) {
            $elements[] = new ListElement($index, optional: false, type: Type::string());
        }

        return Type::fromAtomic(new ListType(Type::never(), $elements, null, nonEmpty: $count > 0));
    }
}
