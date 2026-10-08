<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\CallableSignatureOverride;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\ParameterMetadata;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

use function in_array;
use function strtolower;

/**
 * Accepts a list of entity or revision IDs that mixes integers and strings
 * where core documents `int[]|string[]`.
 *
 * Every ID of one entity type is an integer or every one is a string, but a
 * list built from `$entity->id()` is typed `list<int|string>`, which neither
 * half of `int[]|string[]` accepts. The rest of each signature is read from
 * the declaration the call resolves to, so only the ID lists change.
 *
 * @internal
 */
final class EntityIdListParameterProvider implements MethodReturnTypeProvider, CallableSignatureOverride
{
    private const TARGETS = [
        'Drupal\Core\Entity\RevisionableStorageInterface' => ['loadMultipleRevisions'],
        'Drupal\Core\Entity\EntityRepositoryInterface' => ['getActiveMultiple', 'getCanonicalMultiple'],
        'Drupal\workspaces\WorkspaceAssociationInterface' => [
            'getTrackedEntities',
            'getAssociatedRevisions',
            'getAssociatedInitialRevisions',
            'deleteAssociations',
        ],
        'Drupal\workspaces\WorkspaceTrackerInterface' => [
            'getTrackedEntities',
            'getAllTrackedRevisions',
            'getTrackedInitialRevisions',
            'moveTrackedEntities',
            'deleteTrackedEntities',
        ],
    ];

    /**
     * The ID list parameters, keyed by lowercase method name, which is how
     * the host reports names. `getTrackedEntities()` names its list the same
     * way on both interfaces.
     */
    private const ID_LISTS = [
        'loadmultiplerevisions' => ['$revision_ids'],
        'getactivemultiple' => ['$entity_ids'],
        'getcanonicalmultiple' => ['$entity_ids'],
        'gettrackedentities' => ['$entity_ids'],
        'getassociatedrevisions' => ['$entity_ids'],
        'getassociatedinitialrevisions' => ['$entity_ids'],
        'deleteassociations' => ['$entity_ids', '$revision_ids'],
        'getalltrackedrevisions' => ['$entity_ids'],
        'gettrackedinitialrevisions' => ['$entity_ids'],
        'movetrackedentities' => ['$entity_ids'],
        'deletetrackedentities' => ['$entity_ids', '$revision_ids'],
    ];

    public function getTargets(): array
    {
        $targets = [];
        foreach (self::TARGETS as $class => $methods) {
            foreach ($methods as $method) {
                $targets[] = MethodTarget::exact($class, $method);
            }
        }

        return $targets;
    }

    /**
     * The return type stays the declared one.
     */
    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return null;
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        $invocation = $context->invocation;
        $lists = self::ID_LISTS[strtolower($invocation->name)] ?? null;
        if ($lists === null || $invocation->declaringClass === null) {
            return null;
        }

        $declared = $context->codebase->getMethod($invocation->declaringClass, $invocation->name);
        if ($declared === null) {
            return null;
        }

        $parameters = [];
        foreach ($declared->parameters as $parameter) {
            $parameters[] = self::parameter($parameter, $lists);
        }

        return new EffectiveCallableSignature($parameters);
    }

    /**
     * The parameter as declared, with a mixed ID list when it is one of the
     * lists.
     *
     * @param list<string> $lists
     */
    private static function parameter(ParameterMetadata $parameter, array $lists): CallableParameter
    {
        $type = $parameter->type->type ?? $parameter->declaredType?->type;
        if (in_array($parameter->name, $lists, strict: true)) {
            $id = Type::union(Type::int(), Type::string());
            $type = Types::includesNull($type)
                ? Type::union(Type::array($id, $id), Type::null())
                : Type::array($id, $id);
        }

        return new CallableParameter(
            name: $parameter->name,
            type: $type,
            byReference: $parameter->flags->contains(MetadataFlags::BY_REFERENCE),
            variadic: $parameter->flags->contains(MetadataFlags::VARIADIC),
            hasDefault: $parameter->flags->contains(MetadataFlags::HAS_DEFAULT),
        );
    }
}
