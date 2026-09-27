<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AnyObjectType;
use Mago\Sdk\Analyzer\Type\AtomicType;
use Mago\Sdk\Analyzer\Type\EnumType;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;
use Mago\Sdk\Analyzer\Type\ListType;
use Mago\Sdk\Analyzer\Type\MixedType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;
use Mago\Sdk\Analyzer\Type\SimpleAtomicType;
use Mago\Sdk\Analyzer\Type\SimpleAtomicTypeKind;
use Mago\Sdk\Analyzer\Type\StringType;

use function in_array;

/**
 * Whether a PHPUnit assertion on one value passes for every member of the
 * value's type.
 *
 * A member of unknown shape, such as a template, never passes, so the answer
 * errs toward keeping the assertion. The match has one arm per assertion
 * method; splitting it up would hide the table, not simplify it.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class AlwaysPasses
{
    private function __construct() {}

    public static function check(string $method, Type $value): bool
    {
        foreach ($value->atomicTypes as $atomic) {
            if (!self::member($method, $atomic)) {
                return false;
            }
        }

        return true;
    }

    private static function member(string $method, AtomicType $atomic): bool
    {
        $kind = $atomic instanceof ScalarType ? $atomic->kind : null;
        $refinement = $atomic instanceof ScalarType ? $atomic->refinement : null;

        return match ($method) {
            'assertNotNull' => self::isNonNull($atomic),
            'assertNull' => $atomic instanceof SimpleAtomicType && $atomic->kind === SimpleAtomicTypeKind::Null,
            'assertTrue' => $kind === ScalarTypeKind::Boolean && $refinement === true,
            'assertFalse' => $kind === ScalarTypeKind::Boolean && $refinement === false,
            'assertNotTrue' => self::isNever($atomic, bool: true),
            'assertNotFalse' => self::isNever($atomic, bool: false),
            'assertIsArray' => $atomic instanceof KeyedArrayType || $atomic instanceof ListType,
            'assertIsBool' => $kind === ScalarTypeKind::Boolean,
            'assertIsFloat' => $kind === ScalarTypeKind::Float,
            'assertIsInt' => $kind === ScalarTypeKind::Integer,
            'assertIsNumeric' => self::isNumeric($atomic),
            'assertIsObject' => self::isObject($atomic),
            'assertIsScalar' => $kind !== null,
            'assertIsString' => in_array(
                $kind,
                [ScalarTypeKind::String, ScalarTypeKind::ClassLikeString],
                strict: true,
            ),
            default => false,
        };
    }

    /**
     * Whether the member can never be null. A template or any other member
     * of unknown shape might be.
     */
    private static function isNonNull(AtomicType $atomic): bool
    {
        return (
            self::isObject($atomic)
            || $atomic instanceof ScalarType
            || $atomic instanceof KeyedArrayType
            || $atomic instanceof ListType
            || $atomic instanceof MixedType
            && $atomic->nonNull
        );
    }

    /**
     * Whether the member can never be the given bool. Null passes: it is not
     * identical to either.
     */
    private static function isNever(AtomicType $atomic, bool $bool): bool
    {
        if ($atomic instanceof SimpleAtomicType) {
            return $atomic->kind === SimpleAtomicTypeKind::Null;
        }

        if ($atomic instanceof ScalarType) {
            return match ($atomic->kind) {
                ScalarTypeKind::Scalar => false,
                ScalarTypeKind::Boolean => $atomic->refinement === !$bool,
                default => true,
            };
        }

        return self::isObject($atomic) || $atomic instanceof KeyedArrayType || $atomic instanceof ListType;
    }

    private static function isNumeric(AtomicType $atomic): bool
    {
        if (!$atomic instanceof ScalarType) {
            return false;
        }

        return match ($atomic->kind) {
            ScalarTypeKind::Integer, ScalarTypeKind::Float, ScalarTypeKind::Numeric => true,
            ScalarTypeKind::String => $atomic->refinement instanceof StringType && $atomic->refinement->numeric,
            default => false,
        };
    }

    private static function isObject(AtomicType $atomic): bool
    {
        return $atomic instanceof NamedObjectType || $atomic instanceof AnyObjectType || $atomic instanceof EnumType;
    }
}
