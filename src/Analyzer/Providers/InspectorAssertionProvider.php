<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Assertion\TypeAssertion;
use Mago\Sdk\Analyzer\Assertion\TypeAssertionKind;
use Mago\Sdk\Analyzer\AssertionProviderContext;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\InvocationAssertions;
use Mago\Sdk\Analyzer\MethodAssertionProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use Mago\Sdk\Analyzer\Type\CallableSignature;
use Mago\Sdk\Analyzer\Type\CallableType;
use Mago\Sdk\Analyzer\Type\IterableType;
use Mago\Sdk\Analyzer\Type\MixedTruthiness;
use Mago\Sdk\Analyzer\Type\MixedType;
use Mago\Sdk\Analyzer\Type\ScalarType;
use Mago\Sdk\Analyzer\Type\ScalarTypeKind;

use function count;
use function strtolower;

/**
 * Narrows the value an `Inspector::assert*()` call checks, when the call is
 * true, as inside `assert()`.
 *
 * Ports phpstan-drupal's InspectorTypeExtension, with each type read off what
 * the method tests: `assertAllNumeric()` uses `is_numeric()`, so numeric
 * strings count, and a strict array has the keys 0 to n-1 in order, which is
 * a list. `assertAll()` takes any callable, so it only says the value is
 * iterable, and it narrows a `mixed` value alone. A false call says nothing.
 *
 * @internal
 */
final class InspectorAssertionProvider implements MethodAssertionProvider
{
    private const INSPECTOR = 'Drupal\Component\Assertion\Inspector';

    /**
     * The parameter each method checks, keyed by lowercased name, which is how
     * the host reports names.
     */
    private const CHECKED = [
        'assertall' => '$traversable',
        'assertallstrings' => '$traversable',
        'assertallstringable' => '$traversable',
        'assertstringable' => '$string',
        'assertallarrays' => '$traversable',
        'assertstrictarray' => '$array',
        'assertallstrictarrays' => '$traversable',
        'assertallhavekey' => '$traversable',
        'assertallintegers' => '$traversable',
        'assertallfloat' => '$traversable',
        'assertallcallable' => '$traversable',
        'assertallnotempty' => '$traversable',
        'assertallnumeric' => '$traversable',
        'assertallmatch' => '$traversable',
        'assertallregularexpressionmatch' => '$traversable',
        'assertallobjects' => '$traversable',
    ];

    public function getTargets(): array
    {
        $targets = [];
        foreach (self::CHECKED as $method => $_) {
            $targets[] = MethodTarget::exact(self::INSPECTOR, $method);
        }

        return $targets;
    }

    public function getAssertions(AssertionProviderContext $context): ?InvocationAssertions
    {
        $invocation = $context->invocation;
        $type = match (strtolower($invocation->name)) {
            'assertall' => self::anyIterable($invocation),
            'assertallstrings', 'assertallmatch', 'assertallregularexpressionmatch' => self::iterable(Type::string()),
            'assertallstringable' => self::iterable(self::stringable()),
            'assertstringable' => self::stringable(),
            'assertallarrays', 'assertallhavekey' => self::iterable(self::anyArray()),
            'assertstrictarray' => Type::list(Type::mixed()),
            'assertallstrictarrays' => self::iterable(Type::list(Type::mixed())),
            'assertallintegers' => self::iterable(Type::int()),
            'assertallfloat' => self::iterable(Type::float()),
            'assertallcallable' => self::iterable(self::anyCallable()),
            'assertallnotempty' => self::iterable(Type::fromAtomic(
                new MixedType(false, true, false, MixedTruthiness::Truthy),
            )),
            'assertallnumeric' => self::iterable(Type::fromAtomic(new ScalarType(ScalarTypeKind::Numeric))),
            'assertallobjects' => self::iterable(self::objects($invocation)),
            default => null,
        };
        $parameter = self::CHECKED[strtolower($invocation->name)] ?? null;
        if ($type === null || $parameter === null) {
            return null;
        }

        // An `IsType` fact takes one atomic type. The host reads several facts
        // for one parameter as either-or, as it reads a docblock
        // `@psalm-assert string|Stringable $x`, so a union becomes one fact
        // per member.
        $facts = [];
        foreach ($type->atomicTypes as $atomic) {
            $facts[] = new TypeAssertion(TypeAssertionKind::IsType, Type::fromAtomic($atomic));
        }

        return new InvocationAssertions(ifTrueAssertions: [$parameter => $facts]);
    }

    /**
     * `assertAll()` with any callable: iterable, for a value that is `mixed`.
     */
    private static function anyIterable(Invocation $invocation): ?Type
    {
        $atomics = $invocation->getArgument(1, 'traversable')?->type->atomicTypes ?? [];

        return count($atomics) === 1 && $atomics[0] instanceof MixedType ? self::iterable(Type::mixed()) : null;
    }

    /**
     * The classes `assertAllObjects()` names, or any object when it names
     * none or one of them is not a literal class name.
     */
    private static function objects(Invocation $invocation): Type
    {
        $classes = [];
        for ($position = 1; ($argument = $invocation->getArgument($position)) !== null; $position++) {
            $type = $argument->type;
            $class = $type === null ? null : $type->getLiteralClassString() ?? $type->getLiteralString();
            if ($class === null || $class === '') {
                return Type::object();
            }

            $classes[] = Type::namedObject($class);
        }

        return Types::union($classes) ?? Type::object();
    }

    /**
     * A callable that takes any arguments, as `callable(mixed...): mixed`.
     */
    private static function anyCallable(): Type
    {
        $arguments = new CallableParameter(type: Type::mixed(), variadic: true);

        return Type::fromAtomic(
            new CallableType(new CallableSignature(false, false, [$arguments], Type::mixed(), null, []), null),
        );
    }

    private static function iterable(Type $value): Type
    {
        return Type::fromAtomic(new IterableType(Type::mixed(), $value, null));
    }

    private static function stringable(): Type
    {
        return Type::union(Type::string(), Type::namedObject('Stringable'));
    }

    private static function anyArray(): Type
    {
        return Type::array(Type::fromAtomic(new ScalarType(ScalarTypeKind::ArrayKey)), Type::mixed());
    }
}
