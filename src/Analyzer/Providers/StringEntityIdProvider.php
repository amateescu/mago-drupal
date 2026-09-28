<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\AnalysisMemo;
use amateescu\MagoDrupal\Internal\EntityIdField;
use amateescu\MagoDrupal\Internal\EntityTypeIndex;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

use function in_array;
use function strtolower;

/**
 * Types `id()` as a string, or null before the entity has one, on content
 * entities whose ID field is a string.
 *
 * `EntityInterface::id()` is `int|string|null` for every entity type, and an
 * integer ID comes back from storage as a numeric string, so only an entity
 * type that defines its ID field as a string can be narrowed, which
 * `EntityIdField` reads. For an interface, every entity class implementing
 * it has to agree, so `WorkspaceInterface` is narrowed and
 * `ContentEntityInterface` is not.
 *
 * @internal
 */
final class StringEntityIdProvider implements MethodReturnTypeProvider
{
    /**
     * Per lowercased class or interface name, whether its ID is a string.
     *
     * @var AnalysisMemo<bool>
     */
    private AnalysisMemo $names;

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {
        $this->names = new AnalysisMemo();
    }

    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Entity\EntityInterface', 'id')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $receiver = $context->invocation->receiverType;
        if ($receiver === null) {
            return null;
        }

        foreach ($receiver->atomicTypes as $atomic) {
            if (!$atomic instanceof NamedObjectType || !$this->hasStringId($context->codebase, $atomic->name)) {
                return null;
            }
        }

        return Type::union(Type::string(), Type::null());
    }

    /**
     * Whether every entity class the name stands for has a string ID.
     */
    private function hasStringId(Codebase $codebase, string $name): bool
    {
        $compute = fn(): bool => $this->computeHasStringId($codebase, $name);

        return $this->names->get($codebase, strtolower($name), $compute);
    }

    /**
     * Reads the answer for `hasStringId()` from the codebase.
     */
    private function computeHasStringId(Codebase $codebase, string $name): bool
    {
        $class = $codebase->getClassLike($name);
        if ($class === null) {
            return false;
        }

        if ($class->kind !== ClassLikeKind::Interface) {
            return EntityIdField::isString($codebase, $class->name);
        }

        $implementers = 0;
        foreach (($this->index)($codebase)->classes() as $entityClass) {
            $metadata = $codebase->getClassLike($entityClass);
            if ($metadata === null || !in_array($class->name, $metadata->parentInterfaces, strict: true)) {
                continue;
            }

            if (!EntityIdField::isString($codebase, $metadata->name)) {
                return false;
            }

            $implementers++;
        }

        return $implementers > 0;
    }
}
