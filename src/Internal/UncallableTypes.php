<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AtomicType;
use Mago\Sdk\Analyzer\Type\EnumType;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;

use function in_array;

/**
 * Says whether a value of a type can never be called.
 *
 * Only types that PHP can never call count: an int, a float, a bool, null,
 * an array that can't be a two-element pair, and an object whose class and
 * descendants have no `__invoke()`. A string may name a function or a
 * method, and `mixed` may be anything, so neither counts.
 *
 * @internal
 */
final class UncallableTypes
{
    private const SCALARS = [ScalarTypeKind::Integer, ScalarTypeKind::Float, ScalarTypeKind::Boolean];

    private const INVOKE = '__invoke';

    private function __construct() {}

    /**
     * Whether every member of the type can never be called.
     */
    public static function never(Codebase $codebase, Type $type): bool
    {
        if ($type->atomicTypes === []) {
            return false;
        }

        foreach ($type->atomicTypes as $atomic) {
            if (!self::atomic($codebase, $atomic)) {
                return false;
            }
        }

        return true;
    }

    private static function atomic(Codebase $codebase, AtomicType $atomic): bool
    {
        return match (true) {
            $atomic instanceof ScalarType => in_array($atomic->kind, self::SCALARS, strict: true),
            $atomic instanceof SimpleAtomicType => $atomic->kind === SimpleAtomicTypeKind::Null,
            $atomic instanceof KeyedArrayType => !CallablePairs::keyed($atomic),
            $atomic instanceof ListType => !CallablePairs::list($atomic),
            $atomic instanceof NamedObjectType => $atomic->intersections === null
                && !self::invokable($codebase, $atomic->name),
            $atomic instanceof EnumType => !self::invokable($codebase, $atomic->name),
            default => false,
        };
    }

    /**
     * Whether an object of the class may have `__invoke()`: the class has
     * it, or the class can have descendants and one in the codebase has it.
     * An unknown class may be anything.
     */
    private static function invokable(Codebase $codebase, string $name): bool
    {
        $class = $codebase->getClassLike($name);
        if ($class === null || $class->hasIncompleteHierarchy()) {
            return true;
        }

        if ($codebase->findMethods(class: $class->name, name: self::INVOKE, fields: 0) !== []) {
            return true;
        }

        $final = $class->kind === ClassLikeKind::Enum || $class->flags->contains(MetadataFlags::FINAL);

        return !$final && $codebase->findMethods(descendantsOf: $class->name, name: self::INVOKE, fields: 0) !== [];
    }
}
