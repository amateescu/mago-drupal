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
 * Types `id()` as a string, or null before the entity has one, on config
 * entities and on content entities whose ID field is a string.
 *
 * `EntityInterface::id()` is `int|string|null` for every entity type. A
 * config entity's ID is its machine name. An integer ID comes back from
 * storage as a numeric string, so a content entity type is narrowed only when
 * it defines its ID field as a string, which `EntityIdField` reads. For an
 * interface that is neither, every entity class implementing it has to have a
 * string ID, so `WorkspaceInterface` is narrowed and
 * `ContentEntityInterface` is not.
 *
 * The target is `EntityInterface::id()` because core declares `id()` there
 * and on `EntityBase`, and the host matches on the declaring class. An
 * override that declares a type without an integer in it, such as
 * `: string`, keeps its own type.
 *
 * @internal
 */
final class EntityIdProvider implements MethodReturnTypeProvider
{
    private const CONFIG_ENTITY = 'drupal\core\config\entity\configentityinterface';

    /**
     * Per lowercased class or interface name, whether its ID is a string.
     *
     * @var AnalysisMemo<bool>
     */
    private readonly AnalysisMemo $names;

    /**
     * The entity classes, lowercased, each with its ancestors and whether its
     * ID is a string, in one entry.
     *
     * @var AnalysisMemo<array<string, array{list<string>, bool}>>
     */
    private readonly AnalysisMemo $entityClasses;

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {
        $this->names = new AnalysisMemo();
        $this->entityClasses = new AnalysisMemo();
    }

    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Entity\EntityInterface', 'id')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $receiver = $context->invocation->receiverType;
        if (
            $receiver === null
            || EntityIdField::declaredWithoutInteger($context->codebase, $context->invocation->declaringClass)
        ) {
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
     * Whether every entity the name stands for has a string ID.
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

        if (
            $class->name === self::CONFIG_ENTITY
            || in_array(self::CONFIG_ENTITY, $class->parentInterfaces, strict: true)
        ) {
            return true;
        }

        if ($class->kind !== ClassLikeKind::Interface) {
            return EntityIdField::isString($codebase, $class->name);
        }

        $implementers = 0;
        foreach ($this->entityClasses($codebase) as [$ancestors, $stringId]) {
            if (!in_array($class->name, $ancestors, strict: true)) {
                continue;
            }

            if (!$stringId) {
                return false;
            }

            $implementers++;
        }

        return $implementers > 0;
    }

    /**
     * The entity classes of the index with their ancestors and whether their
     * ID is a string, read once per analysis.
     *
     * @return array<string, array{list<string>, bool}>
     */
    private function entityClasses(Codebase $codebase): array
    {
        return $this->entityClasses->get($codebase, 'all', function () use ($codebase): array {
            $classes = ($this->index)($codebase)->classes();
            $entityClasses = [];
            foreach ($classes === [] ? [] : $codebase->getMultipleClassAncestors($classes) as $i => $ancestors) {
                $class = $classes[$i];
                $config = in_array(self::CONFIG_ENTITY, $ancestors, strict: true);
                $entityClasses[strtolower($class)] = [
                    $ancestors,
                    $config || EntityIdField::isString($codebase, $class),
                ];
            }

            return $entityClasses;
        });
    }
}
