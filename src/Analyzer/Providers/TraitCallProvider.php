<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\AnalysisMemo;
use amateescu\MagoDrupal\Internal\TraitDeclarations;
use amateescu\MagoDrupal\Internal\TraitMethods;
use amateescu\MagoDrupal\Internal\TraitRoots;
use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\UndeclaredReturnTypeProvider;

use function count;
use function strtolower;

/**
 * Types a call on `$this` in a trait to a method every class using the trait
 * has.
 *
 * Mago analyzes a trait once, on its own, so `$this->getEntity()` in a trait
 * that does not declare `getEntity()` is a missing method and its result is
 * `mixed`, although every class using the trait provides it. PHPStan checks
 * the trait's body in each using class instead. The provider answers only
 * when every class using the trait has the method, with the union of their
 * return types and the parameters they agree on, as the root classes in
 * TraitRoots declare them. A class that lacks the method keeps the report.
 *
 * @internal
 */
final class TraitCallProvider implements
    MethodReturnTypeProvider,
    CallableSignatureProvider,
    UndeclaredReturnTypeProvider
{
    /**
     * The answer for each trait and method, by lowercased names.
     *
     * @var AnalysisMemo<TraitMethods|null>
     */
    private readonly AnalysisMemo $answers;

    public function __construct(
        private readonly TraitRoots $roots,
    ) {
        $this->answers = new AnalysisMemo();
    }

    public function getTargets(): array
    {
        return [new MethodTarget('*', '*')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $receiver = $invocation->receiverType;

        return $receiver === null ? null : $this->answer($context->codebase, $invocation)?->returnType($receiver);
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $methods = $this->answer($context->codebase, $context->invocation);

        return $methods === null ? null : new EffectiveCallableSignature($methods->parameters());
    }

    /**
     * The methods behind a call on a trait's `$this`, or null when the
     * receiver is not a trait or a class using it lacks the method.
     */
    private function answer(Codebase $codebase, Invocation $invocation): ?TraitMethods
    {
        $trait = self::trait($invocation);
        if ($trait === null) {
            return null;
        }

        $method = $invocation->name;

        return $this->answers->get($codebase, strtolower($trait) . "\0" . strtolower($method), function () use (
            $codebase,
            $trait,
            $method,
        ): ?TraitMethods {
            $methods = TraitDeclarations::of($codebase, $this->roots->of($codebase, $trait), $method);

            return $methods === null ? null : new TraitMethods($methods);
        });
    }

    /**
     * The class-like the receiver names, when it is a single named object.
     */
    private static function trait(Invocation $invocation): ?string
    {
        $atomics = $invocation->receiverType->atomicTypes ?? [];
        if (count($atomics) !== 1 || !$atomics[0] instanceof NamedObjectType) {
            return null;
        }

        return $atomics[0]->name;
    }
}
