<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\UndeclaredReturnTypeProvider;

use function array_map;
use function in_array;
use function strtolower;

/**
 * Types the calls that set up a prophecy.
 *
 * `$prophecy->getFoo()` on an `ObjectProphecy<Foo>` goes through
 * `__call()`, which returns a `MethodProphecy`. Mago reports every such call
 * as undocumented, since the class has no `@method` tag for it. This provider
 * answers for a method the prophesized class declares, or for any method when
 * the class is not known. A method the class lacks stays reported, as Prophecy
 * throws for it. A double of an interface that is `Traversable` but neither an
 * `Iterator` nor an `IteratorAggregate` also has the `Iterator` methods, which
 * Prophecy's `TraversablePatch` adds.
 *
 * A prophecy documented as `@var Foo|ProphecyInterface` is left alone. A
 * prophecy is never a `Foo`, so the `Foo` half types the call as the real
 * method's result, and the docblock is what needs fixing, to
 * `ObjectProphecy<Foo>`.
 *
 * @internal
 */
final class ProphecyCallProvider implements MethodReturnTypeProvider, UndeclaredReturnTypeProvider
{
    private const OBJECT_PROPHECY = 'Prophecy\Prophecy\ObjectProphecy';

    private const METHOD_PROPHECY = 'Prophecy\Prophecy\MethodProphecy';

    /**
     * The methods `TraversablePatch` adds, by lowercased name.
     */
    private const ITERATOR_METHODS = ['current', 'key', 'next', 'rewind', 'valid'];

    public function getTargets(): array
    {
        return [MethodTarget::allMethods(self::OBJECT_PROPHECY)];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return self::prophesied($context->invocation, $context->codebase)
            ? Type::namedObject(self::METHOD_PROPHECY)
            : null;
    }

    /**
     * Whether the prophesized class declares the method, or is not known.
     */
    private static function prophesied(Invocation $invocation, Codebase $codebase): bool
    {
        $classes = [];
        foreach ($invocation->receiverType->atomicTypes ?? [] as $atomic) {
            if (!$atomic instanceof NamedObjectType) {
                continue;
            }

            foreach ($atomic->parameters ?? [] as $parameter) {
                $classes = [...$classes, ...Types::names($parameter)];
            }
        }

        foreach ($classes as $class) {
            if (strtolower($class) !== 'object' && $codebase->getDeclaringMethod($class, $invocation->name) !== null) {
                return true;
            }
        }

        if (in_array($invocation->name, self::ITERATOR_METHODS, strict: true)) {
            foreach ($classes as $class) {
                if (self::patchedTraversable($codebase, $class)) {
                    return true;
                }
            }
        }

        return $classes === [];
    }

    /**
     * Whether the class is `Traversable` without being an `IteratorAggregate`.
     *
     * Only an interface can be, since PHP makes a class implement one of
     * `Iterator` or `IteratorAggregate`, and an `Iterator` declares the methods.
     */
    private static function patchedTraversable(Codebase $codebase, string $class): bool
    {
        $ancestors = array_map(strtolower(...), [$class, ...$codebase->getClassAncestors($class)]);

        return (
            in_array('traversable', $ancestors, strict: true)
            && !in_array('iteratoraggregate', $ancestors, strict: true)
        );
    }
}
