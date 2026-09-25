<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\EntityTypeIndex;
use amateescu\MagoDrupal\Internal\EntityTypeKind;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;

/**
 * Reports a config entity type that leaves `config_export` out.
 *
 * Ports phpstan-drupal's ConfigEntityConfigExportRule. Without the key the
 * entity's properties are exported by reflection, which Drupal deprecated.
 * The entity type index records the key for attributes and annotations alike.
 *
 * @internal
 */
final class ConfigEntityExportCheck implements MetadataCheck
{
    public const CODE = 'config-entity-export';

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {}

    public function mentionsAny(): array
    {
        return [];
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        if ($class->class->flags->contains(MetadataFlags::ABSTRACT)) {
            return;
        }

        $type = ($this->index)($class->codebase)->byClass($class->name());
        if ($type === null || $type->kind !== EntityTypeKind::Config || $type->exportsConfig) {
            return;
        }

        $reporter->error(self::CODE, Reporter::issue(
            "Config entity type {$class->name()} defines no config_export.",
            $class->class->nameLocation ?? $class->class->location,
            'List the exported properties in the config_export key.',
            'https://www.drupal.org/node/2481909',
        ));
    }
}
