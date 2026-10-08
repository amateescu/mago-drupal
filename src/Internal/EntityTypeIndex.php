<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;

use function array_filter;
use function array_key_exists;
use function array_slice;
use function array_values;
use function count;
use function str_ends_with;
use function strtolower;
use function substr;

/**
 * Maps entity type ids to their entity class and handler classes.
 *
 * Read from Mago's frozen class metadata instead of the filesystem: every
 * descendant of `EntityInterface` is fetched and the ones carrying an entity
 * type attribute are kept. Vendor code is indexed too, so a contrib workspace
 * sees core's entity types. Legacy `@ContentEntityType` annotations, read off
 * the files under the Drupal root, come in through `merge()`. Not read:
 * classes whose parent chain Mago could not resolve, since those never show
 * up as descendants.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class EntityTypeIndex
{
    public const ENTITY_INTERFACE = 'Drupal\Core\Entity\EntityInterface';

    /**
     * Fetch classes from the host in slices of this size.
     */
    private const BATCH = 200;

    /**
     * @param array<non-empty-string, EntityTypeDefinition> $types
     * @param array<string, list<non-empty-string>> $byStorage Lowercased
     *   storage class to the entity type ids it serves.
     * @param array<string, non-empty-string> $byClass Lowercased entity class
     *   to entity type id.
     * @param array<string, true> $ambiguous Ids two classes declare, which
     *   are known but name no definition, as with plugins.
     */
    private function __construct(
        private readonly array $types,
        private readonly array $byStorage,
        private readonly array $byClass,
        private readonly array $ambiguous = [],
    ) {}

    /**
     * @param list<string> $names Every descendant of `EntityInterface`.
     */
    public static function fromDescendants(Codebase $codebase, array $names): self
    {
        $definitions = [];
        for ($offset = 0, $total = count($names); $offset < $total; $offset += self::BATCH) {
            foreach ($codebase->getMultipleClasses(array_slice($names, $offset, self::BATCH)) as $class) {
                $definition = $class === null
                    ? null
                    : EntityTypeAttribute::read($class->originalName, $class->attributes);
                if ($definition !== null) {
                    $definitions[] = $definition;
                }
            }
        }

        return self::fromDefinitions($definitions);
    }

    /**
     * @param list<EntityTypeDefinition> $definitions
     * @param array<string, true> $ambiguous Ids already known to be ambiguous.
     */
    public static function fromDefinitions(array $definitions, array $ambiguous = []): self
    {
        $types = [];
        $byStorage = [];
        $byClass = [];
        // Class-keyed maps are lowercased: PHP class names are case-insensitive
        // and Mago reports some of them lowercased.
        foreach ($definitions as $definition) {
            $other = $types[$definition->id] ?? null;
            if ($other !== null && strtolower($other->class) !== strtolower($definition->class)) {
                $ambiguous[$definition->id] = true;
            }

            $types[$definition->id] = $definition;
            $byClass[strtolower($definition->class)] = $definition->id;
            $storage = $definition->storage();
            if ($storage !== null) {
                $byStorage[strtolower($storage)][] = $definition->id;
            }
        }

        // An ambiguous id names no definition, so no class or storage leads
        // to it either.
        foreach ($ambiguous as $id => $_) {
            unset($types[$id]);
        }

        $byClass = array_filter($byClass, static fn(string $id): bool => !array_key_exists($id, $ambiguous));
        foreach ($byStorage as $storage => $ids) {
            $kept = array_values(array_filter($ids, static fn(string $id): bool => !array_key_exists($id, $ambiguous)));
            if ($kept === []) {
                unset($byStorage[$storage]);
                continue;
            }

            $byStorage[$storage] = $kept;
        }

        return new self($types, $byStorage, $byClass, $ambiguous);
    }

    /**
     * Adds definitions for ids and classes this index does not have yet, so
     * an attribute wins over an annotation on the same class.
     *
     * @param list<EntityTypeDefinition> $definitions
     */
    public function merge(array $definitions): self
    {
        $added = [];
        foreach ($definitions as $definition) {
            if ($this->declares($definition->id) || array_key_exists(strtolower($definition->class), $this->byClass)) {
                continue;
            }

            $added[] = $definition;
        }

        return (
            $added === [] ? $this : self::fromDefinitions([...array_values($this->types), ...$added], $this->ambiguous)
        );
    }

    public function get(string $id): ?EntityTypeDefinition
    {
        return $this->types[$id] ?? null;
    }

    /**
     * Whether some class declares the id, even when two do and no definition
     * can be handed back for it.
     */
    public function declares(string $id): bool
    {
        return array_key_exists($id, $this->types) || array_key_exists($id, $this->ambiguous);
    }

    /**
     * The entity type an entity class declares.
     */
    public function byClass(string $class): ?EntityTypeDefinition
    {
        $id = $this->byClass[strtolower($class)] ?? null;

        return $id === null ? null : $this->types[$id];
    }

    /**
     * The one entity type a storage class serves, or null when the class is
     * shared or unknown. Core names a storage's interface after the class
     * (`RoleStorageInterface` for `RoleStorage`), so an interface resolves
     * through that name.
     */
    public function byStorage(string $class): ?EntityTypeDefinition
    {
        $ids = $this->byStorage[strtolower($class)] ?? [];
        if ($ids === [] && str_ends_with(strtolower($class), 'storageinterface')) {
            $ids = $this->byStorage[strtolower(substr($class, offset: 0, length: -9))] ?? [];
        }

        return count($ids) === 1 ? $this->types[$ids[0]] : null;
    }

    /**
     * The entity classes of every entity type.
     *
     * @return list<string>
     */
    public function classes(): array
    {
        $classes = [];
        foreach ($this->types as $definition) {
            $classes[] = $definition->class;
        }

        return $classes;
    }

    public function count(): int
    {
        return count($this->types);
    }
}
