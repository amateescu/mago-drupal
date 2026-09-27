<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\PHPUnit\AlwaysPasses;
use Mago\Sdk\Analyzer\Type;
use PHPUnit\Framework\TestCase;

final class AlwaysPassesTest extends TestCase
{
    public function testNullChecks(): void
    {
        $object = Type::namedObject('Drupal\node\NodeInterface');
        $nullable = Type::union($object, Type::null());

        self::assertTrue(AlwaysPasses::check('assertNotNull', $object));
        self::assertTrue(AlwaysPasses::check('assertNotNull', Type::string()));
        self::assertFalse(AlwaysPasses::check('assertNotNull', $nullable));
        self::assertFalse(AlwaysPasses::check('assertNotNull', Type::mixed()));
        self::assertTrue(AlwaysPasses::check('assertNull', Type::null()));
        self::assertFalse(AlwaysPasses::check('assertNull', $nullable));
    }

    public function testBoolChecks(): void
    {
        self::assertTrue(AlwaysPasses::check('assertTrue', Type::true()));
        self::assertFalse(AlwaysPasses::check('assertTrue', Type::bool()));
        self::assertTrue(AlwaysPasses::check('assertFalse', Type::false()));
        self::assertTrue(AlwaysPasses::check('assertNotFalse', Type::union(Type::int(), Type::string())));
        self::assertTrue(AlwaysPasses::check('assertNotFalse', Type::union(Type::true(), Type::null())));
        self::assertFalse(AlwaysPasses::check('assertNotFalse', Type::bool()));
        self::assertFalse(AlwaysPasses::check('assertNotTrue', Type::bool()));
        self::assertTrue(AlwaysPasses::check('assertNotTrue', Type::false()));
    }

    public function testTypeChecks(): void
    {
        self::assertTrue(AlwaysPasses::check('assertIsArray', Type::list(Type::string())));
        self::assertTrue(AlwaysPasses::check('assertIsString', Type::literalString('x')));
        self::assertTrue(AlwaysPasses::check('assertIsInt', Type::literalInt(3)));
        self::assertTrue(AlwaysPasses::check('assertIsNumeric', Type::float()));
        self::assertTrue(AlwaysPasses::check('assertIsScalar', Type::union(Type::int(), Type::string())));
        self::assertTrue(AlwaysPasses::check('assertIsObject', Type::object()));
        self::assertFalse(AlwaysPasses::check('assertIsString', Type::union(Type::string(), Type::null())));
        self::assertFalse(AlwaysPasses::check('assertIsNumeric', Type::string()));
        self::assertFalse(AlwaysPasses::check('assertIsArray', Type::mixed()));
    }

    /**
     * assertInstanceOf() goes through the host's type comparison instead.
     */
    public function testUnknownAssertionNeverPasses(): void
    {
        self::assertFalse(AlwaysPasses::check('assertInstanceOf', Type::object()));
    }
}
