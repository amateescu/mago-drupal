<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use Mago\Sdk\Analyzer\Assertion\TypeAssertion;
use Mago\Sdk\Analyzer\Assertion\TypeAssertionKind;
use Mago\Sdk\Analyzer\AssertionProviderContext;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\InvocationAssertions;
use Mago\Sdk\Analyzer\MethodAssertionProvider;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

use function count;
use function strtolower;

/**
 * Adds the interface or class a prophecy is told to implement or extend to
 * its prophesized type.
 *
 * Prophecy declares `willImplement()` and `willExtend()` with
 * `@phpstan-this-out static<T&U>`, which Mago does not apply, so a method of
 * the added type is not found on the prophecy afterwards. The provider types
 * the call as `ObjectProphecy<T&U>` for a chain, and narrows the receiver to
 * it for a call on a variable.
 *
 * @todo Remove this provider once Mago applies a `@this-out` that narrows
 *   the receiver.
 *
 * @see https://github.com/carthage-software/mago/issues/2395
 *
 * @internal
 */
final class ProphecyThisOutProvider implements MethodReturnTypeProvider, MethodAssertionProvider
{
    private const OBJECT_PROPHECY = 'Prophecy\Prophecy\ObjectProphecy';

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::OBJECT_PROPHECY, 'willImplement'),
            MethodTarget::exact(self::OBJECT_PROPHECY, 'willExtend'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return self::extended($context->invocation);
    }

    public function getAssertions(AssertionProviderContext $context): ?InvocationAssertions
    {
        $extended = self::extended($context->invocation);
        if ($extended === null) {
            return null;
        }

        return new InvocationAssertions([
            InvocationAssertions::RECEIVER => [new TypeAssertion(TypeAssertionKind::IsType, $extended)],
        ]);
    }

    /**
     * `ObjectProphecy<T&U>` for a receiver `ObjectProphecy<T>` and an
     * argument `U::class`, or null when either is not known.
     */
    private static function extended(Invocation $invocation): ?Type
    {
        $added = $invocation->getArgument(0, 'interface', 'class')?->type?->getLiteralClassString();
        $prophecy = self::prophecy($invocation->receiverType);
        if ($added === null || $prophecy === null) {
            return null;
        }

        $addedType = new NamedObjectType($added, null, null, false, false, null, false);
        $prophesized = $prophecy->parameters[0] ?? null;
        $current =
            $prophesized !== null && count($prophesized->atomicTypes) === 1 ? $prophesized->atomicTypes[0] : null;

        // An unknown or plain `object` prophecy becomes a prophecy of the
        // added type alone.
        $combined = $current instanceof NamedObjectType
            ? new NamedObjectType(
                $current->name,
                $current->parameters,
                $current->variances,
                false,
                false,
                [...($current->intersections ?? []), $addedType],
                $current->remappedParameters,
            )
            : $addedType;

        return Type::namedObject(self::OBJECT_PROPHECY, Type::fromAtomic($combined));
    }

    /**
     * The receiver when it is one `ObjectProphecy`, since an assertion
     * replaces the receiver with one type.
     */
    private static function prophecy(?Type $receiver): ?NamedObjectType
    {
        $atomic = $receiver !== null && count($receiver->atomicTypes) === 1 ? $receiver->atomicTypes[0] : null;
        if (!$atomic instanceof NamedObjectType || strtolower($atomic->name) !== strtolower(self::OBJECT_PROPHECY)) {
            return null;
        }

        return $atomic;
    }
}
