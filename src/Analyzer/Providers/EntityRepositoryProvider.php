<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\EntityTypeIndex;
use amateescu\MagoDrupal\Internal\Types;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function in_array;
use function strtolower;

/**
 * Types entity repository lookups by their entity type id argument.
 *
 * `getTranslationFromContext($node)` gives back the class it was handed.
 *
 * @internal
 */
final class EntityRepositoryProvider implements MethodReturnTypeProvider
{
    private const REPOSITORY = 'Drupal\Core\Entity\EntityRepositoryInterface';

    /**
     * Lowercase method names, which is how the host reports them.
     */
    private const NULLABLE = ['loadentitybyuuid', 'loadentitybyconfigtarget', 'getactive', 'getcanonical'];

    private const LISTS = ['getactivemultiple', 'getcanonicalmultiple'];

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {}

    public function getTargets(): array
    {
        $targets = [MethodTarget::exact(self::REPOSITORY, 'getTranslationFromContext')];
        foreach ([...self::NULLABLE, ...self::LISTS] as $method) {
            $targets[] = MethodTarget::exact(self::REPOSITORY, $method);
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $method = strtolower($invocation->name);
        if ($method === 'gettranslationfromcontext') {
            // The translation is an object of the entity's own class. An
            // argument that may be null or anything else keeps the declared
            // type.
            $entity = $invocation->getArgument(0, 'entity')?->type;

            return Types::objectsOnly($entity) ? $entity : null;
        }

        $id = $invocation->getArgument(0, 'entity_type_id')?->type?->getLiteralString();
        $type = $id === null ? null : ($this->index)($context->codebase)->get($id);
        if ($type === null || !EntityTypes::known($context->codebase, $type->class)) {
            return null;
        }

        $entity = Type::namedObject($type->class);
        if (in_array($method, self::LISTS, strict: true)) {
            return Type::array(Type::union(Type::int(), Type::string()), $entity);
        }

        return Type::union($entity, Type::null());
    }
}
