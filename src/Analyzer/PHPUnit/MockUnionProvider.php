<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;
use Mago\Sdk\Analyzer\UndeclaredReturnTypeProvider;

use function count;
use function preg_match;
use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Types `expects()` and `method()` on the mocked half of an `X|MockObject`
 * union.
 *
 * Older tests, Drupal's among them, document mocks as `@var X|MockObject`, a
 * union from before PHP had intersection types. Mago checks each half of a union on its own, so
 * `expects()` is missing on `X`, and the call comes back as `mixed`, which
 * loses the type of the rest of the chain. phpstan-phpunit reads the union as
 * `X&MockObject` instead.
 *
 * Mago asks this provider only when the type does not declare the method, and
 * the provider sees that type alone, not the whole union. So it accepts a call
 * shaped like a mock call: `expects()` with one argument, which Mago then
 * checks against `InvocationOrder`, and `method()` with the name of a method
 * the type has. PHPUnit's own types are left alone, so `expects()` on the
 * `Stub` of an `X|Stub` union or on a `MockBuilder` is still reported. Any
 * other call stays a missing method. PlainMockCallHook reports the calls it
 * answers for on a type that is `X` alone.
 *
 * @internal
 */
final class MockUnionProvider implements
    MethodReturnTypeProvider,
    CallableSignatureProvider,
    UndeclaredReturnTypeProvider
{
    private const EXPECTS = 'expects';

    public const INVOCATION_MOCKER = 'PHPUnit\Framework\MockObject\Builder\InvocationMocker';

    private const INVOCATION_ORDER = 'PHPUnit\Framework\MockObject\Rule\InvocationOrder';

    private const METHOD_NAME = '/^([\'"])([A-Za-z_][A-Za-z0-9_]*)\1$/';

    public const PHPUNIT = 'PHPUnit\\';

    public function getTargets(): array
    {
        return [MethodTarget::anyClass(self::EXPECTS), MethodTarget::anyClass('method')];
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $invocation = $context->invocation;
        if (!self::isMockCall($invocation, $context->codebase)) {
            return null;
        }

        return new EffectiveCallableSignature([
            strtolower($invocation->name) === self::EXPECTS
                ? new CallableParameter('$invocationRule', Type::namedObject(self::INVOCATION_ORDER))
                : new CallableParameter('$constraint'),
        ]);
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return self::isMockCall($context->invocation, $context->codebase)
            ? Type::namedObject(self::INVOCATION_MOCKER)
            : null;
    }

    private static function isMockCall(Invocation $invocation, Codebase $codebase): bool
    {
        $argument = $invocation->getArgument(0);
        if ($argument === null || count($invocation->arguments) !== 1) {
            return false;
        }

        // `expects()` takes any argument, which Mago checks against the
        // parameter type. `method()` must name a method of the mocked type.
        $method = null;
        if (strtolower($invocation->name) !== self::EXPECTS) {
            $matches = [];
            if (preg_match(self::METHOD_NAME, trim($argument->expression), $matches) !== 1) {
                return false;
            }

            $method = $matches[2];
        }

        foreach (Types::names($invocation->receiverType) as $class) {
            if (str_starts_with($class, self::PHPUNIT)) {
                continue;
            }

            if ($method === null || $codebase->getDeclaringMethod($class, $method) !== null) {
                return true;
            }
        }

        return false;
    }
}
