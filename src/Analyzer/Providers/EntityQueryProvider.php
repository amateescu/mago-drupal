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

use function strtolower;

/**
 * Types entity queries from creation to `execute()`.
 *
 * `getQuery()`, `getAggregateQuery()`, `\Drupal::entityQuery()` and
 * `\Drupal::entityQueryAggregate()` return a query tagged with its entity
 * type. `accessCheck()` and `count()` update the tags. `execute()` reads
 * them and returns `int<0, max>` after `count()`, the ids for a query and the
 * grouped rows for an aggregate.
 *
 * @internal
 */
final class EntityQueryProvider implements MethodReturnTypeProvider
{
    private const STORAGE = 'Drupal\Core\Entity\EntityStorageInterface';

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(self::STORAGE, 'getQuery'),
            MethodTarget::exact(self::STORAGE, 'getAggregateQuery'),
            MethodTarget::exact('Drupal', 'entityQuery'),
            MethodTarget::exact('Drupal', 'entityQueryAggregate'),
            MethodTarget::exact(EntityQueries::QUERY, 'accessCheck'),
            MethodTarget::exact(EntityQueries::QUERY, 'count'),
            MethodTarget::exact(EntityQueries::QUERY, 'execute'),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;

        return match (strtolower($invocation->name)) {
            'getquery', 'entityquery' => $this->created($context, EntityQueries::QUERY),
            'getaggregatequery', 'entityqueryaggregate' => $this->created($context, EntityQueries::AGGREGATE),
            'accesscheck' => EntityQueries::retagged($invocation, 2, EntityQueries::accessTag($invocation)),
            'count' => EntityQueries::retagged($invocation, 3, EntityQueries::COUNT),
            'execute' => self::executed($context),
            default => null,
        };
    }

    /**
     * A fresh query. The entity type comes from the storage receiver's tag or
     * from the literal argument; when neither says, the query still gets
     * tagged, with the entity type unknown, so the chain stays tracked. A bare
     * config storage receiver at least says the kind is config.
     */
    private function created(ReturnTypeProviderContext $context, string $class): Type
    {
        $invocation = $context->invocation;
        $index = ($this->index)($context->codebase);
        $definition = EntityTypes::fromReceiver($index, $invocation->receiverType);
        $entityType = $definition?->id;
        if ($invocation->kind->name === 'StaticMethod') {
            $entityType = $invocation->getArgument(0, 'entity_type')?->type?->getLiteralString();
            $definition = $entityType === null ? null : $index->get($entityType);
        }

        if ($entityType === null || $entityType === '') {
            $entityType = EntityQueries::UNKNOWN;
        }

        $kind = $definition === null && EntityQueries::isConfigStorage($invocation->receiverType)
            ? EntityQueries::CONFIG
            : EntityQueries::kindOf($definition?->kind);

        return EntityQueries::tagged($class, $entityType, $kind, EntityQueries::UNCHECKED, EntityQueries::IDS);
    }

    /**
     * The SQL query fetches ids keyed by revision or entity id as strings; a
     * config entity query casts both key and value to string.
     */
    private static function executed(ReturnTypeProviderContext $context): ?Type
    {
        $all = EntityQueries::receiverTags($context->invocation->receiverType);
        if ($all === null) {
            return null;
        }

        // A receiver that may be a counting or a listing query returns either.
        $types = [];
        foreach ($all as [$class, $_, $kind, $_, $result]) {
            $types[] = self::result($class, $kind, $result);
        }

        return Types::union($types);
    }

    /**
     * What `execute()` returns for one kind of query.
     */
    private static function result(string $class, string $kind, string $result): Type
    {
        if ($result === EntityQueries::COUNT) {
            return Type::nonNegativeInt();
        }

        if (strtolower($class) === strtolower(EntityQueries::AGGREGATE)) {
            return Type::list(Type::array(Type::string(), Type::mixed()));
        }

        return $kind === EntityQueries::CONFIG
            ? Type::array(Type::string(), Type::string())
            : Type::array(Type::union(Type::int(), Type::string()), Type::string());
    }
}
