<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

use function count;

/**
 * Types an undocumented `create()` of a container-injected class as `static`.
 *
 * `ContainerInjectionInterface::create()` promises a new instance of the
 * called class but documents no return type, so a `create()` that only says
 * `{@inheritdoc}` returns `mixed`. That also hides `AutowireTrait`'s
 * `@return static`: Mago looks for the documented method up the parent
 * classes by the class that declares it, so a method a parent gets from a
 * trait is skipped and the interface wins. `FormBase` and `ControllerBase`
 * use the trait, so `parent::create($container)` in most forms and
 * controllers is `mixed`. A `create()` with a return type of its own keeps it.
 *
 * @internal
 */
final class ContainerInjectionProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\DependencyInjection\ContainerInjectionInterface', 'create')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $receiver = $invocation->receiverType;
        $class = $receiver !== null && count($receiver->atomicTypes) === 1 ? $receiver->atomicTypes[0] : null;
        if (!$class instanceof NamedObjectType) {
            return null;
        }

        $declaring = $invocation->declaringClass;
        $method = $declaring === null ? null : $context->codebase->getDeclaringMethod($declaring, $invocation->name);
        if ($method === null || $method->returnType !== null) {
            return null;
        }

        // `parent::` and `static::` calls arrive as `$this`, and a new
        // instance is not `$this`.
        return Type::fromAtomic(
            new NamedObjectType(
                $class->name,
                $class->parameters,
                $class->variances,
                $class->static,
                false,
                $class->intersections,
                $class->remappedParameters,
            ),
        );
    }
}
