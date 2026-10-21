<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\Entity\EntityTypeIndex;
use amateescu\MagoDrupal\Internal\Entity\EntityTypeKind;
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

    private const REVISIONABLE_ENTITY = 'Drupal\Core\Entity\RevisionableInterface';

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
            return self::revisionOfArgument($context);
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
            return self::revisionOfArgument($context);
        }

        $entity = Type::namedObject($type->class);
        if (in_array($method, self::NULLABLE, strict: true)) {
            return Type::union($entity, Type::null());
        }

        if (in_array($method, self::LISTS, strict: true)) {
            // Config entity ids are machine names, so the keys are strings,
            // as phpstan-drupal types them. A content entity id can be an
            // integer or a string.
            $key = $type->kind === EntityTypeKind::Config ? Type::string() : Type::union(Type::int(), Type::string());

            return Type::array($key, $entity);
        }

        return in_array($method, self::PLAIN, strict: true) ? $entity : null;
    }

    /**
     * The type of the entity handed to `createRevision()`, for a storage
     * whose entity class isn't known. The method returns a new revision of
     * the entity it gets, so the class stays the same. Core documents the
     * result as `RevisionableInterface`.
     */
    private static function revisionOfArgument(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        if (strtolower($invocation->name) !== 'createrevision') {
            return null;
        }

        $argument = $invocation->getArgument(0, 'entity')?->type;
        if (
            $argument === null
            || !$context->types->isContainedBy($argument, Type::namedObject(self::REVISIONABLE_ENTITY))
        ) {
            return null;
        }

        return $argument;
    }
}
