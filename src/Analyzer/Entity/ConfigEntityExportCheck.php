<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Entity;

use amateescu\MagoDrupal\Analyzer\Support\ClassMentions;
use amateescu\MagoDrupal\Analyzer\Support\MentionedCheck;
use amateescu\MagoDrupal\Analyzer\Support\Reporter;
use amateescu\MagoDrupal\Internal\Declaration\ClassFacts;
use amateescu\MagoDrupal\Internal\Declaration\ClassNames;
use amateescu\MagoDrupal\Internal\Entity\EntityTypeIndex;
use amateescu\MagoDrupal\Internal\Entity\EntityTypeKind;
use amateescu\MagoDrupal\Internal\Plugin\AnnotatedDeclarations;
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
final class ConfigEntityExportCheck implements MentionedCheck
{
    public const CODE = 'config-entity-export';

    private const ATTRIBUTE = 'Drupal\Core\Entity\Attribute\ConfigEntityType';

    /**
     * @param Closure(Codebase): EntityTypeIndex $index
     * @param Closure(): AnnotatedDeclarations $annotated
     */
    public function __construct(
        private readonly Closure $index,
        private readonly Closure $annotated,
    ) {}

    public function textGate(): ?string
    {
        return null;
    }

    /**
     * An annotated config entity type doesn't have an attribute to mention,
     * so the annotated classes read off disk say whether the class is one.
     */
    public function mentionedIn(ClassMentions $class): bool
    {
        if ($class->names(self::ATTRIBUTE)) {
            return true;
        }

        $classes = [];
        foreach (($this->annotated)()->entityTypes as $definition) {
            if ($definition->kind !== EntityTypeKind::Config) {
                continue;
            }

            $classes[] = $definition->class;
        }

        return $classes !== [] && ClassNames::anyIs($class->candidates, $classes);
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
            "Config entity type {$class->name()} doesn't define config_export.",
            $class->class->nameLocation ?? $class->class->location,
            'List the exported properties in the config_export key.',
            'https://www.drupal.org/node/2481909',
        ));
    }
}
