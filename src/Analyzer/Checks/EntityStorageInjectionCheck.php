<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\StorageTypes;
use amateescu\MagoDrupal\Internal\TraitRoots;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;

use function in_array;
use function ltrim;
use function str_replace;
use function strtolower;

/**
 * Reports entity storages held as constructor parameters or properties.
 *
 * Ports phpstan-drupal's EntityStorageDirectInjectionRule and
 * EntityStoragePropertyAssignmentRule. A storage handler can be swapped by
 * another module at runtime, so code asks the entity type manager for it at
 * the call site instead of holding on to one. A property counts by its
 * effective type, so a `@var` docblock on an untyped property is read too.
 * An entity handler may hold its own storage: the entity type manager hands
 * a list builder or views data its storage on purpose, and the handler keeps
 * it in a property. Core names it `$storage`, or `$storage_controller` in
 * views data, so a handler's storage under any other name is reported like
 * anywhere else. A trait is checked on its own and counts as a handler when
 * every class using it is one.
 *
 * @internal
 */
final class EntityStorageInjectionCheck implements MetadataCheck
{
    public const INJECTION_CODE = 'entity-storage-injection';

    public const PROPERTY_CODE = 'entity-storage-property';

    public const HELP = 'Inject \Drupal\Core\Entity\EntityTypeManagerInterface and call getStorage() where the storage is used.';

    public const LINK = 'https://mglaman.dev/blog/dependency-injection-anti-patterns-drupal';

    private const HANDLER = 'Drupal\Core\Entity\EntityHandlerInterface';

    /**
     * The names of a handler's own storage, lowercased and without
     * underscores.
     */
    private const OWN_STORAGE = ['storage', 'storagecontroller'];

    public function __construct(
        private readonly TraitRoots $traitRoots,
    ) {}

    public function mentionsAny(): array
    {
        return [];
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $handler = $this->handler($class);
        $constructor = $class->constructor();
        foreach ($constructor === null ? [] : HookMethods::parameters($constructor) as $parameter) {
            if (
                $handler && self::own($parameter->name)
                || !StorageTypes::any($class->codebase, Types::names($parameter->declaredType?->type))
            ) {
                continue;
            }

            $reporter->warning(self::INJECTION_CODE, Reporter::issue(
                "Entity storage is injected through {$parameter->name}.",
                $parameter->location,
                self::HELP,
                self::LINK,
            ));
        }

        foreach ($class->properties() as $property) {
            $location = $property->nameLocation ?? $property->location;
            $type = $property->type ?? $property->declaredType;
            if (
                $location === null
                || $handler && self::own($property->name)
                || !StorageTypes::any($class->codebase, Types::names($type?->type))
            ) {
                continue;
            }

            $reporter->warning(self::PROPERTY_CODE, Reporter::issue(
                "Entity storage is kept in {$property->name}.",
                $location,
                self::HELP,
                self::LINK,
            ));
        }
    }

    /**
     * Whether the class is an entity handler, or a trait whose users all are.
     */
    private function handler(ClassFacts $class): bool
    {
        return (
            $class->class->kind === ClassLikeKind::Trait
                ? $this->traitRoots->allImplement($class->codebase, $class->name(), self::HANDLER)
                : $class->implementsAny([self::HANDLER])
        );
    }

    /**
     * Whether a handler's parameter or property is named for its own storage.
     */
    private static function own(string $name): bool
    {
        $name = str_replace(search: '_', replace: '', subject: ltrim($name, characters: '$'));

        return in_array(strtolower($name), self::OWN_STORAGE, strict: true);
    }
}
