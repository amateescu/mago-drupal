<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use Mago\Sdk\Analyzer\Assertion\SimpleAssertion;
use Mago\Sdk\Analyzer\Assertion\SimpleAssertionKind;
use Mago\Sdk\Analyzer\AssertionProviderContext;
use Mago\Sdk\Analyzer\InvocationAssertions;
use Mago\Sdk\Analyzer\MethodAssertionProvider;
use Mago\Sdk\Analyzer\MethodTarget;

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

        return new InvocationAssertions(['$actual' => [new SimpleAssertion($kind)]]);
    }
}
