<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

use function array_key_exists;
use function count;
use function max;
use function min;

use const PHP_INT_MAX;

/**
 * The parameters of a call that several declarations of one method must all
 * accept, as a trait's `$this->method()` call does for the classes using it.
 *
 * As many as the shortest declaration without a variadic takes, or the
 * longest one when every declaration is variadic, optional only where every
 * declaration gives a default and nothing required follows, by reference
 * where any declaration takes one, and typed only where every declaration
 * gives the same type. The SDK rejects a variadic with a
 * default, a name used twice and a required parameter after an optional one,
 * so none of those is built.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class SharedParameters
{
    private function __construct() {}

    /**
     * @param non-empty-list<FunctionLikeMetadata> $methods
     * @return list<CallableParameter>
     */
    public static function of(array $methods): array
    {
        $count = PHP_INT_MAX;
        $longest = 0;
        foreach ($methods as $method) {
            $count = self::isVariadic($method) ? $count : min($count, count($method->parameters));
            $longest = max($longest, count($method->parameters));
        }

        // When every declaration is variadic, the longest one decides, and
        // the variadic of each shorter one covers the positions after its
        // own.
        $count = $count === PHP_INT_MAX ? $longest : $count;
        $optional = [];
        $required = false;
        for ($position = $count - 1; $position >= 0; $position--) {
            $optional[$position] = !$required && self::optional($methods, $position);
            $required = $required || !$optional[$position];
        }

        $parameters = [];
        $names = [];
        for ($position = 0; $position < $count; $position++) {
            // A variadic of the first declaration covers several positions.
            $name = self::parameter($methods[0], $position)->name ?? '';
            $name = $name === '' || array_key_exists($name, $names) ? self::freeName($names, $position) : $name;
            $names[$name] = true;
            $parameters[] = self::at($methods, $position, $name, $optional[$position] ?? false);
        }

        return $parameters;
    }

    /**
     * A name for the position that no earlier parameter has, since the
     * first declaration's own names may include `$arg1` and the like.
     *
     * @param array<string, true> $names
     */
    private static function freeName(array $names, int $position): string
    {
        $name = '$arg' . $position;
        for ($suffix = 1; array_key_exists($name, $names); $suffix++) {
            $name = '$arg' . $position . '_' . $suffix;
        }

        return $name;
    }

    /**
     * Whether every declaration gives a default at the position, or takes it
     * through a variadic.
     *
     * @param non-empty-list<FunctionLikeMetadata> $methods
     */
    private static function optional(array $methods, int $position): bool
    {
        foreach ($methods as $method) {
            $bits = self::parameter($method, $position)->flags->bits ?? 0;
            if (($bits & (MetadataFlags::HAS_DEFAULT | MetadataFlags::VARIADIC)) === 0) {
                return false;
            }
        }

        return true;
    }

    /**
     * The parameter at one position.
     *
     * @param non-empty-list<FunctionLikeMetadata> $methods
     */
    private static function at(array $methods, int $position, string $name, bool $optional): CallableParameter
    {
        $all = -1;
        $any = 0;
        $types = [];
        foreach ($methods as $method) {
            $parameter = self::parameter($method, $position);
            $bits = $parameter === null ? 0 : $parameter->flags->bits;
            $all &= $bits;
            $any |= $bits;
            $types[] = $method->templates === [] ? $parameter?->type?->type : null;
        }

        $variadic = ($all & MetadataFlags::VARIADIC) !== 0;

        return new CallableParameter(
            name: $name,
            type: self::agreed($types),
            byReference: ($any & MetadataFlags::BY_REFERENCE) !== 0,
            variadic: $variadic,
            hasDefault: $optional && !$variadic,
        );
    }

    /**
     * The one type every declaration gives, or null when they differ or one
     * gives none.
     *
     * @param list<Type|null> $types
     */
    private static function agreed(array $types): ?Type
    {
        $agreed = $types[0] ?? null;
        foreach ($types as $type) {
            if ($type === null || $agreed === null || $agreed->encode() !== $type->encode()) {
                return null;
            }
        }

        return $agreed;
    }

    /**
     * The parameter an argument at the position goes to: the one declared
     * there, or a variadic one before it.
     */
    private static function parameter(FunctionLikeMetadata $method, int $position): ?ParameterMetadata
    {
        $parameters = $method->parameters;
        if (array_key_exists($position, $parameters)) {
            return $parameters[$position];
        }

        return self::isVariadic($method) ? $parameters[count($parameters) - 1] : null;
    }

    /**
     * Whether the method's last parameter is variadic.
     */
    private static function isVariadic(FunctionLikeMetadata $method): bool
    {
        $parameters = $method->parameters;

        return $parameters !== [] && $parameters[count($parameters) - 1]->flags->contains(MetadataFlags::VARIADIC);
    }
}
