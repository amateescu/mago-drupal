<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Closure;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AnyObjectType;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

use function array_slice;
use function array_values;
use function count;

/**
 * Questions the checks ask of a declared type.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class Types
{
    private function __construct() {}

    /**
     * Every class-like name in the type, intersection members included.
     *
     * @return list<non-empty-string>
     */
    public static function names(?Type $type): array
    {
        $names = [];
        foreach ($type === null ? [] : $type->atomicTypes as $atomic) {
            if (!$atomic instanceof NamedObjectType) {
                continue;
            }

            $names = [...$names, ...self::namesOf($atomic)];
        }

        return $names;
    }

    /**
     * Whether the type is `array`, `?array` or a union holding an array.
     */
    public static function isArray(?Type $type): bool
    {
        foreach ($type === null ? [] : $type->atomicTypes as $atomic) {
            if ($atomic instanceof KeyedArrayType || $atomic instanceof ListType) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the type is `string`, `?string` or a union holding a string.
     */
    public static function isString(?Type $type): bool
    {
        foreach ($type === null ? [] : $type->atomicTypes as $atomic) {
            if ($atomic instanceof ScalarType && $atomic->kind === ScalarTypeKind::String) {
                return true;
            }
        }

        return false;
    }

    /**
     * The one answer every object in the type gives, or null when one of them
     * gives none, two give different ones, or a member is not an object at
     * all. A null member is skipped: a call on null is reported on its own.
     *
     * @template T of object|string
     * @param Closure(NamedObjectType): (T|null) $resolve
     * @return T|null
     */
    public static function agreed(?Type $type, Closure $resolve): object|string|null
    {
        $found = null;
        foreach ($type === null ? [] : $type->atomicTypes as $atomic) {
            if ($atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null) {
                continue;
            }

            $answer = $atomic instanceof NamedObjectType ? $resolve($atomic) : null;
            if ($answer === null || $found !== null && $found !== $answer) {
                return null;
            }

            $found = $answer;
        }

        return $found;
    }

    /**
     * Whether every member of the type is an object, so it cannot be null,
     * mixed or a scalar.
     */
    public static function objectsOnly(?Type $type): bool
    {
        if ($type === null) {
            return false;
        }

        foreach ($type->atomicTypes as $atomic) {
            if (!$atomic instanceof NamedObjectType && !$atomic instanceof AnyObjectType) {
                return false;
            }
        }

        return true;
    }

    /**
     * The type with its `static` and `$this` members replaced by the receiver.
     */
    public static function withReceiver(Type $type, Type $receiver): Type
    {
        $others = [];
        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof NamedObjectType && ($atomic->static || $atomic->isThis)) {
                continue;
            }

            $others[] = $atomic;
        }

        if (count($others) === count($type->atomicTypes)) {
            return $type;
        }

        return $others === [] ? $receiver : Type::fromAtomics(...$receiver->atomicTypes, ...$others);
    }

    /**
     * The union of the distinct types, the type itself when only one is left,
     * or null for none.
     *
     * @param list<Type> $types
     */
    public static function union(array $types): ?Type
    {
        $distinct = [];
        foreach ($types as $type) {
            $distinct[$type->encode()] = $type;
        }

        $distinct = array_values($distinct);

        return match (count($distinct)) {
            0 => null,
            1 => $distinct[0],
            default => Type::union($distinct[0], $distinct[1], ...array_slice($distinct, offset: 2)),
        };
    }

    /**
     * Whether the type holds `null`: `?Foo`, `Foo|null`, or the type of a
     * `= NULL` default.
     */
    public static function includesNull(?Type $type): bool
    {
        foreach ($type === null ? [] : $type->atomicTypes as $atomic) {
            if ($atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the type can only hold scalars, arrays or null, so it never
     * carries an object. An absent type can hold anything.
     */
    public static function isScalarOnly(?Type $type): bool
    {
        if ($type === null) {
            return false;
        }

        foreach ($type->atomicTypes as $atomic) {
            if (
                !$atomic instanceof ScalarType
                && !$atomic instanceof SimpleAtomicType
                && !$atomic instanceof KeyedArrayType
                && !$atomic instanceof ListType
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<non-empty-string>
     */
    private static function namesOf(NamedObjectType $atomic): array
    {
        $names = $atomic->name === '' ? [] : [$atomic->name];
        foreach ($atomic->intersections ?? [] as $member) {
            if (!$member instanceof NamedObjectType) {
                continue;
            }

            $names = [...$names, ...self::namesOf($member)];
        }

        return $names;
    }
}
