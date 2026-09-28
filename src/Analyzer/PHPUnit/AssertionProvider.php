<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Assertion\SimpleAssertion;
use Mago\Sdk\Analyzer\Assertion\SimpleAssertionKind;
use Mago\Sdk\Analyzer\AssertionProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\InvocationAssertions;
use Mago\Sdk\Analyzer\MethodAssertionProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\AnyObjectType;

use function array_intersect;
use function strtolower;

/**
 * Narrows the value handed to PHPUnit's emptiness assertions.
 *
 * PHPUnit puts `@phpstan-assert` on `assertNotNull()` and `assertInstanceOf()`,
 * which Mago reads, but not on `assertNotEmpty()` and `assertEmpty()`. Many
 * tests reach for those two instead, Drupal's among them, so a value loaded in
 * a test stays nullable and every call on it afterwards is reported.
 *
 * @internal
 */
final class AssertionProvider implements MethodAssertionProvider
{
    private const ASSERT = 'PHPUnit\Framework\Assert';

    /**
     * Interfaces whose objects PHPUnit's `IsEmpty` counts, lowercased.
     */
    private const COUNTED = ['countable', 'traversable'];

    /**
     * Keyed by lowercase method name, which is how the host reports names.
     */
    private const KINDS = [
        'assertnotempty' => SimpleAssertionKind::NonEmpty,
        'assertempty' => SimpleAssertionKind::Empty,
    ];

    public function getTargets(): array
    {
        $targets = [];
        foreach (self::KINDS as $method => $_) {
            $targets[] = MethodTarget::exact(self::ASSERT, $method);
        }

        return $targets;
    }

    public function getAssertions(AssertionProviderContext $context): ?InvocationAssertions
    {
        $kind = self::KINDS[strtolower($context->invocation->name)] ?? null;
        if ($kind === null) {
            return null;
        }

        // PHPUnit counts a Countable or Traversable, so an empty one passes
        // `assertEmpty()` though PHP's `empty()` never holds for an object.
        $actual = $context->invocation->getArgument(0, 'actual')?->type;
        if ($kind === SimpleAssertionKind::Empty && self::mayCount($context->codebase, $actual)) {
            return null;
        }

        return new InvocationAssertions(['$actual' => [new SimpleAssertion($kind)]]);
    }

    /**
     * Whether the value may be an object PHPUnit counts: a plain `object`, or
     * a class that is Countable or Traversable.
     */
    private static function mayCount(Codebase $codebase, ?Type $type): bool
    {
        foreach ($type === null ? [] : $type->atomicTypes as $atomic) {
            if ($atomic instanceof AnyObjectType) {
                return true;
            }
        }

        $names = Types::names($type);
        foreach ($names === [] ? [] : $codebase->getMultipleClassLikes($names) as $class) {
            $lineage = $class === null ? [] : [$class->name, ...$class->parentInterfaces];
            if (array_intersect(self::COUNTED, $lineage) !== []) {
                return true;
            }
        }

        return false;
    }
}
