<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\EntityTypeIndex;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function in_array;
use function strtolower;

/**
 * Types what an entity storage hands back, from the entity type its receiver
 * serves.
 *
 * @internal
 */
final class EntityStorageProvider implements MethodReturnTypeProvider
{
    private const STORAGE = 'Drupal\Core\Entity\EntityStorageInterface';

    private const REVISIONABLE = 'Drupal\Core\Entity\RevisionableStorageInterface';

    /**
     * Lowercase method names, which is how the host reports them.
     */
    private const NULLABLE = ['load', 'loadunchanged', 'loadrevision', 'loadrevisionunchanged'];

    private const LISTS = ['loadmultiple', 'loadbyproperties', 'loadmultiplerevisions'];

    private const PLAIN = ['create', 'createrevision'];

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {}

    public function getTargets(): array
    {
        $targets = [
            MethodTarget::exact(self::STORAGE, 'getEntityTypeId'),
            MethodTarget::exact(self::STORAGE, 'getEntityType'),
        ];
        foreach (['load', 'loadUnchanged', 'loadMultiple', 'loadByProperties', 'create'] as $method) {
            $targets[] = MethodTarget::exact(self::STORAGE, $method);
        }

        foreach (['loadRevision', 'loadRevisionUnchanged', 'loadMultipleRevisions', 'createRevision'] as $method) {
            $targets[] = MethodTarget::exact(self::REVISIONABLE, $method);
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $type = EntityTypes::fromReceiver(($this->index)($context->codebase), $invocation->receiverType);
        if ($type === null) {
            return null;
        }

        $method = strtolower($invocation->name);
        if ($method === 'getentitytypeid') {
            return Type::literalString($type->id);
        }

        if ($method === 'getentitytype') {
            $interface = $type->definitionInterface();

            return $interface === null ? null : Type::namedObject($interface);
        }

        if (!EntityTypes::known($context->codebase, $type->class)) {
            return null;
        }

        $entity = Type::namedObject($type->class);
        if (in_array($method, self::NULLABLE, strict: true)) {
            return Type::union($entity, Type::null());
        }

        if (in_array($method, self::LISTS, strict: true)) {
            return Type::array(Type::union(Type::int(), Type::string()), $entity);
        }

        return in_array($method, self::PLAIN, strict: true) ? $entity : null;
    }
}
