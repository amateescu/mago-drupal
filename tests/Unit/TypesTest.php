<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Type\Types;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use PHPUnit\Framework\TestCase;

final class TypesTest extends TestCase
{
    /**
     * `static` and `$this` become the receiver; other members stay.
     */
    public function testReplacesTheSelfTypesWithTheReceiver(): void
    {
        $receiver = Type::namedObject('Drupal\example\ExampleTrait');
        $static = new NamedObjectType('Drupal\example\Base', null, null, true, false, null, false);
        $thisType = new NamedObjectType('Drupal\example\Base', null, null, false, true, null, false);

        self::assertSame($receiver, Types::withReceiver(Type::fromAtomic($static), $receiver));
        self::assertSame($receiver, Types::withReceiver(Type::fromAtomic($thisType), $receiver));

        $nullable = Types::withReceiver(Type::fromAtomics($static, ...Type::null()->atomicTypes), $receiver);
        self::assertSame('Drupal\example\ExampleTrait|null', (string) $nullable);

        $string = Type::string();
        self::assertSame($string, Types::withReceiver($string, $receiver));
        $base = Type::namedObject('Drupal\example\Base');
        self::assertSame($base, Types::withReceiver($base, $receiver));
    }

    /**
     * Every member has to be a literal string, or there isn't an answer.
     */
    public function testReadsTheValuesOfALiteralStringUnion(): void
    {
        $union = Type::union(Type::literalString('node'), Type::literalString('user'));
        self::assertSame(['node', 'user'], Types::literalStrings($union));
        self::assertSame(['node'], Types::literalStrings(Type::literalString('node')));

        self::assertNull(Types::literalStrings(Type::union(Type::literalString('node'), Type::string())));
        self::assertNull(Types::literalStrings(Type::union(Type::literalString('node'), Type::null())));
        self::assertNull(Types::literalStrings(Type::string()));
        self::assertNull(Types::literalStrings(null));
    }
}
