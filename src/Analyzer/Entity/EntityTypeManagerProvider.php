<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Entity;

use amateescu\MagoDrupal\Internal\Entity\EntityTypeDefinition;
use amateescu\MagoDrupal\Internal\Entity\EntityTypeIndex;
use amateescu\MagoDrupal\Internal\Syntax\Arguments;
use amateescu\MagoDrupal\Internal\Type\Types;
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
 * A union of literal ids, such as `'node'|'user'`, gives the union of their
 * handlers, and the declared type once one of them is unknown.
 * `getDefinition('node')` narrows to the content or config entity type
 * interface, and `getEntityTypeFromClass(Node::class)` to the literal id. An
 * entity type id the index does not know still drops the declared null,
 * because the call throws rather than returning null.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
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

        $ids = Types::literalStrings($invocation->getArgument(0, 'entity_type_id')?->type) ?? [];
        if ($method === 'getdefinition') {
            $interfaces = [];
            foreach ($ids as $id) {
                $interfaces[] = Type::namedObject($index->get($id)?->definitionInterface() ?? self::ENTITY_TYPE);
            }

            return self::definition(
                $invocation,
                $interfaces === [] ? [Type::namedObject(self::ENTITY_TYPE)] : $interfaces,
            );
        }

        $handlers = [];
        foreach ($ids as $id) {
            $handler = self::handler($context, $index->get($id), $method);
            if ($handler === null) {
                $handlers = [];
                break;
            }

            $handlers[] = $handler;
        }

        if ($handlers === []) {
            return $method === 'gethandler'
                ? EntityTypes::handlerInterface($invocation->getArgument(1, 'handler_type')?->type?->getLiteralString())
                : null;
        }

        return Types::union($handlers);
    }

    /**
     * The handler a getter returns for one entity type, or null when the type
     * or its handler class is not known.
     */
    private static function handler(
        ReturnTypeProviderContext $context,
        ?EntityTypeDefinition $type,
        string $method,
    ): ?Type {
        $invocation = $context->invocation;
        $class = $type === null
            ? null
            : match ($method) {
                'getformobject' => $type->handler(
                    'form',
                    $invocation->getArgument(1, 'operation')?->type?->getLiteralString(),
                ),
                'gethandler' => $type->handler(
                    $invocation->getArgument(1, 'handler_type')?->type?->getLiteralString() ?? '',
                ),
                default => $type->handler(self::HANDLER_GETTERS[$method] ?? ''),
            };

        return $type === null || $class === null || !EntityTypes::known($context->codebase, $class)
            ? null
            : EntityTypes::handler($class, $type);
    }

    /**
     * `getDefinition()` returns null instead of throwing when its second
     * argument is false, so a literal false keeps null and a computed flag
     * keeps the declared type.
     *
     * @param non-empty-list<Type> $interfaces
     */
    private static function definition(Invocation $invocation, array $interfaces): ?Type
    {
        return match (Arguments::literalBool($invocation, 1, true, 'exception_on_invalid')) {
            true => Types::union($interfaces),
            false => Types::union([...$interfaces, Type::null()]),
            null => null,
        };
    }
}
