<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ClassLikeStringType;
use Mago\Sdk\Analyzer\Type\ClassLikeStringVariant;
use Mago\Sdk\Analyzer\Type\ScalarType;

use function count;

/**
 * Types `createHandlerInstance(X::class)` as an `X`.
 *
 * Core documents the method as returning `object`, though it builds an
 * instance of the class it is handed, through `createInstance()` when the
 * class implements `EntityHandlerInterface` and with `new` otherwise. A
 * literal class name gives that class, a `class-string<X>` gives an `X`, and
 * a plain string keeps core's type.
 *
 * @internal
 */
final class HandlerInstanceProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Entity\EntityTypeManagerInterface', 'createHandlerInstance')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $class = $context->invocation->getArgument(0, 'class')?->type;
        if ($class === null || count($class->atomicTypes) !== 1) {
            return null;
        }

        // A class-string arrives as a string refined to one.
        $atomic = $class->atomicTypes[0];
        $string = $atomic instanceof ScalarType ? $atomic->refinement : null;
        if (!$string instanceof ClassLikeStringType) {
            return null;
        }

        return match (true) {
            $string->variant === ClassLikeStringVariant::Literal && $string->literal !== null && $string->literal !== ''
                => Type::namedObject($string->literal),
            $string->variant === ClassLikeStringVariant::OfType && $string->constraint !== null
                => Type::fromAtomic($string->constraint),
            default => null,
        };
    }
}
