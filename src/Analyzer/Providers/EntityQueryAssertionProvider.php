<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\Assertion\TypeAssertion;
use Mago\Sdk\Analyzer\Assertion\TypeAssertionKind;
use Mago\Sdk\Analyzer\AssertionProviderContext;
use Mago\Sdk\Analyzer\InvocationAssertions;
use Mago\Sdk\Analyzer\MethodAssertionProvider;
use Mago\Sdk\Analyzer\MethodTarget;

use function count;
use function strtolower;

/**
 * Retags the query variable itself when `accessCheck()` or `count()` is a
 * statement of its own.
 *
 * The return type provider covers the fluent chain; `$query->accessCheck(TRUE);`
 * on its own line discards the return value, so the variable would keep its
 * old tags. A receiver assertion narrows the receiver variable instead, the
 * way phpstan-drupal's type-specifying extension does.
 *
 * @internal
 */
final class EntityQueryAssertionProvider implements MethodAssertionProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact(EntityQueries::QUERY, 'accessCheck'),
            MethodTarget::exact(EntityQueries::QUERY, 'count'),
        ];
    }

    public function getAssertions(AssertionProviderContext $context): ?InvocationAssertions
    {
        $invocation = $context->invocation;
        $retagged = match (strtolower($invocation->name)) {
            'accesscheck' => EntityQueries::retagged($invocation, 2, EntityQueries::accessTag($invocation)),
            'count' => EntityQueries::retagged($invocation, 3, EntityQueries::COUNT),
            default => null,
        };
        // An assertion replaces the receiver with one type, so a receiver
        // whose members retag differently is left alone.
        if ($retagged === null || count($retagged->atomicTypes) !== 1) {
            return null;
        }

        return new InvocationAssertions([
            InvocationAssertions::RECEIVER => [new TypeAssertion(TypeAssertionKind::IsType, $retagged)],
        ]);
    }
}
