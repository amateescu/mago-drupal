<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\EntityTypeDefinition;
use amateescu\MagoDrupal\Internal\EntityTypeIndex;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

use function array_map;
use function in_array;
use function strtolower;

/**
 * Shared helpers for the entity providers.
 *
 * A handler type carries the entity type id as a type parameter, for example
 * `NodeStorage<'node'>`, so a later call on that handler knows which entity
 * type it serves even when the handler class is shared by several types.
 *
 * @internal
 */
final class EntityTypes
{
    private function __construct() {}

    /**
     * A handler object type tagged with its entity type id.
     */
    public static function handler(string $class, EntityTypeDefinition $type): Type
    {
        return Type::namedObject($class, Type::literalString($type->id));
    }

    /**
     * The entity type a handler receiver serves.
     *
     * Reads the id tag first, then falls back to a storage class that only one
     * entity type uses. Every object the receiver may be has to serve the same
     * entity type; one that serves none, such as an untagged storage
     * interface, could load any entity.
     */
    public static function fromReceiver(EntityTypeIndex $index, ?Type $receiver): ?EntityTypeDefinition
    {
        return Types::agreed($receiver, static fn(NamedObjectType $atomic): ?EntityTypeDefinition => self::fromIntersection(
            $index,
            $atomic,
        ));
    }

    /**
     * The entity type an object serves, read from any member of an
     * intersection such as `NodeStorage&MockObject`.
     */
    private static function fromIntersection(EntityTypeIndex $index, NamedObjectType $atomic): ?EntityTypeDefinition
    {
        foreach ([$atomic, ...($atomic->intersections ?? [])] as $member) {
            $type = $member instanceof NamedObjectType ? self::fromNamedObject($index, $member) : null;
            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }

    private static function fromNamedObject(EntityTypeIndex $index, NamedObjectType $atomic): ?EntityTypeDefinition
    {
        $id = ($atomic->parameters[0] ?? null)?->getLiteralString();
        $tagged = $id === null ? null : $index->get($id);
        // The tag is trusted only on a class the index knows as one of that
        // entity type's handlers, so a genuinely generic object with a string
        // parameter is left alone.
        if (
            $tagged !== null
            && in_array(strtolower($atomic->name), array_map(strtolower(...), $tagged->handlers), strict: true)
        ) {
            return $tagged;
        }

        return $index->byStorage($atomic->name);
    }

    /**
     * Whether Mago knows the class, so a handler named by an unscanned module
     * does not surface as a phantom type.
     */
    public static function known(Codebase $codebase, ?string $class): bool
    {
        return $class !== null && $codebase->classLikeExists($class);
    }
}
