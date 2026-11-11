<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Type;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AtomicType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\GenericParameterType;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ScalarType;

use function array_key_exists;
use function array_slice;
use function count;
use function ltrim;

/**
 * Fills in the templates of a generic method that a call binds with a class
 * name, as in `createMock(Foo::class)`.
 *
 * Mago does this itself for a call on a class. A provider that answers for a
 * call has to do it for the type that it returns.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class Templates
{
    private function __construct() {}

    /**
     * The class that the call gives each template of the method, by template
     * name: the class name passed for a `class-string<T>` parameter, as
     * `Foo::class` or as a string that names a class. Null when the call
     * leaves one of the method's templates open.
     *
     * @return array<string, NamedObjectType>|null
     */
    public static function bound(FunctionLikeMetadata $method, Invocation $invocation, Codebase $codebase): ?array
    {
        $bound = [];
        foreach ($method->parameters as $position => $parameter) {
            $atomics = $parameter->type?->type->atomicTypes ?? [];
            $refinement = count($atomics) === 1 && $atomics[0] instanceof ScalarType ? $atomics[0]->refinement : null;
            $template = $refinement instanceof ClassLikeStringType ? $refinement->parameterName : null;
            if ($template === null) {
                continue;
            }

            $argument = $invocation->getArgument($position, ltrim($parameter->name, characters: '$'));
            $class = $argument?->type?->getLiteralClassString();
            $string = $class === null ? $argument?->type?->getLiteralString() : null;
            if ($string !== null && $string !== '' && $codebase->classLikeExists($string)) {
                $class = ltrim($string, characters: '\\');
            }

            if ($class === null || $class === '') {
                return null;
            }

            $bound[$template] = self::named($class, intersections: null);
        }

        foreach ($method->templates as $template) {
            if (!array_key_exists($template->name, $bound)) {
                return null;
            }
        }

        return $bound;
    }

    /**
     * The type with its templates replaced by the classes they are bound
     * to, or null when it names a template that isn't bound. Templates are
     * replaced where a return type usually has them: as the type itself, as
     * a type argument and in an intersection.
     *
     * @param array<string, NamedObjectType> $bound
     */
    public static function resolve(Type $type, array $bound): ?Type
    {
        $atomics = [];
        foreach ($type->atomicTypes as $atomic) {
            $resolved = self::atomic($atomic, $bound);
            if ($resolved === null) {
                return null;
            }

            $atomics[] = $resolved;
        }

        return $atomics === [] ? null : Type::fromAtomics($atomics[0], ...array_slice($atomics, offset: 1));
    }

    /**
     * @param array<string, NamedObjectType> $bound
     */
    private static function atomic(AtomicType $atomic, array $bound): ?AtomicType
    {
        if ($atomic instanceof GenericParameterType) {
            $class = $bound[$atomic->name] ?? null;
            $intersections = self::atomics($atomic->intersections, $bound);

            return $class === null || $intersections === false ? null : self::named($class->name, $intersections);
        }

        if (!$atomic instanceof NamedObjectType) {
            return $atomic;
        }

        $parameters = null;
        foreach ($atomic->parameters ?? [] as $parameter) {
            $resolved = self::resolve($parameter, $bound);
            if ($resolved === null) {
                return null;
            }

            $parameters[] = $resolved;
        }

        $intersections = self::atomics($atomic->intersections, $bound);

        return $intersections === false
            ? null
            : new NamedObjectType(
                $atomic->name,
                $parameters,
                $atomic->variances,
                $atomic->static,
                $atomic->isThis,
                $intersections,
                $atomic->remappedParameters,
            );
    }

    /**
     * The members of an intersection with their templates replaced, or
     * false when one of them isn't bound.
     *
     * @param list<AtomicType>|null $atomics
     * @param array<string, NamedObjectType> $bound
     *
     * @return list<AtomicType>|false|null
     */
    private static function atomics(?array $atomics, array $bound): array|false|null
    {
        if ($atomics === null) {
            return null;
        }

        $resolved = [];
        foreach ($atomics as $atomic) {
            $member = self::atomic($atomic, $bound);
            if ($member === null) {
                return false;
            }

            $resolved[] = $member;
        }

        return $resolved;
    }

    /**
     * @param list<AtomicType>|null $intersections
     */
    private static function named(string $class, ?array $intersections): NamedObjectType
    {
        return new NamedObjectType(
            $class,
            parameters: null,
            variances: null,
            static: false,
            isThis: false,
            intersections: $intersections,
            remappedParameters: false,
        );
    }
}
