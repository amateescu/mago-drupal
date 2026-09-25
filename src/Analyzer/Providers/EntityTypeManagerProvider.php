<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\Arguments;
use amateescu\MagoDrupal\Internal\EntityTypeIndex;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function strtolower;

/**
 * Types the handler getters on the entity type manager.
 *
 * `getStorage('node')` returns `NodeStorage<'node'>` instead of the declared
 * `EntityStorageInterface`; the other getters do the same for their handler.
 * `getDefinition('node')` narrows to the content or config entity type
 * interface, and `getEntityTypeFromClass(Node::class)` to the literal id. An
 * entity type id the index does not know still drops the declared null,
 * because the call throws rather than returning null.
 *
 * @internal
 */
final class EntityTypeManagerProvider implements MethodReturnTypeProvider
{
    private const MANAGER = 'Drupal\Core\Entity\EntityTypeManagerInterface';

    private const REPOSITORY = 'Drupal\Core\Entity\EntityTypeRepositoryInterface';

    private const ENTITY_TYPE = 'Drupal\Core\Entity\EntityTypeInterface';

    /**
     * Keyed by lowercase method name, which is how the host reports names.
     */
    private const HANDLER_GETTERS = [
        'getstorage' => 'storage',
        'getaccesscontrolhandler' => 'access',
        'getviewbuilder' => 'view_builder',
        'getlistbuilder' => 'list_builder',
    ];

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {}

    public function getTargets(): array
    {
        $targets = [
            MethodTarget::exact(self::MANAGER, 'getFormObject'),
            MethodTarget::exact(self::MANAGER, 'getHandler'),
            MethodTarget::exact(self::MANAGER, 'getDefinition'),
            MethodTarget::exact(self::REPOSITORY, 'getEntityTypeFromClass'),
        ];
        foreach (self::HANDLER_GETTERS as $method => $_) {
            $targets[] = MethodTarget::exact(self::MANAGER, $method);
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $index = ($this->index)($context->codebase);
        $method = strtolower($invocation->name);
        if ($method === 'getentitytypefromclass') {
            $class = $invocation->getArgument(0, 'class_name')?->type?->getLiteralString();
            $id = $class === null ? null : $index->byClass($class)?->id;

            return $id === null ? null : Type::literalString($id);
        }

        $id = $invocation->getArgument(0, 'entity_type_id')?->type?->getLiteralString();
        $type = $id === null ? null : $index->get($id);
        if ($method === 'getdefinition') {
            return self::definition($invocation, $type?->definitionInterface() ?? self::ENTITY_TYPE);
        }

        if ($type === null) {
            return null;
        }

        $handler = match ($method) {
            'getformobject' => $type->handler(
                'form',
                $invocation->getArgument(1, 'operation')?->type?->getLiteralString(),
            ),
            'gethandler' => $type->handler(
                $invocation->getArgument(1, 'handler_type')?->type?->getLiteralString() ?? '',
            ),
            default => $type->handler(self::HANDLER_GETTERS[$method] ?? ''),
        };
        if ($handler === null || !EntityTypes::known($context->codebase, $handler)) {
            return null;
        }

        return EntityTypes::handler($handler, $type);
    }

    /**
     * `getDefinition()` returns null instead of throwing when its second
     * argument is false, so a literal false keeps null and a computed flag
     * keeps the declared type.
     */
    private static function definition(Invocation $invocation, string $interface): ?Type
    {
        return match (Arguments::literalBool($invocation, 1, true, 'exception_on_invalid')) {
            true => Type::namedObject($interface),
            false => Type::union(Type::namedObject($interface), Type::null()),
            null => null,
        };
    }
}
