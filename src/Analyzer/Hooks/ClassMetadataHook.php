<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\ConfigEntityExportCheck;
use amateescu\MagoDrupal\Analyzer\Checks\DependencySerializationCheck;
use amateescu\MagoDrupal\Analyzer\Checks\EntityStorageInjectionCheck;
use amateescu\MagoDrupal\Analyzer\Checks\HookMethods;
use amateescu\MagoDrupal\Analyzer\Checks\MetadataCheck;
use amateescu\MagoDrupal\Analyzer\Checks\PluginAnnotationContextCheck;
use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Internal\AnnotatedDeclarations;
use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\ClassNames;
use amateescu\MagoDrupal\Internal\DeclaredClass;
use amateescu\MagoDrupal\Internal\EntityTypeKind;
use amateescu\MagoDrupal\Internal\StorageTypes;
use Closure;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\NodeKind;

use function array_key_exists;
use function array_keys;
use function in_array;
use function strtolower;

/**
 * Runs the class-level checks that need no ancestry on every class, from
 * metadata alone.
 *
 * The hook asks for no subtree, so every class costs the host one node span.
 * The names resolved inside that span say which checks can apply at all: a
 * hook attribute, a storage type, the serialization trait, a config entity
 * type or an annotated plugin. A storage type that only a `@var` docblock
 * names is not a resolved name, so the class text is searched for one too.
 * Only a class mentioning one of those is looked up in the codebase.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:excessive-parameter-list
 */
final class ClassMetadataHook implements NodeAnalysisHook
{
    private const TRAIT = DependencySerializationCheck::TRAIT;

    private const CONFIG_ENTITY_TYPE = 'Drupal\Core\Entity\Attribute\ConfigEntityType';

    /**
     * @param Closure(): AnnotatedDeclarations $annotated
     * @param list<MetadataCheck> $hookChecks Checks on `#[Hook]` methods.
     */
    public function __construct(
        private readonly Closure $annotated,
        private readonly array $hookChecks,
        private readonly EntityStorageInjectionCheck $storage,
        private readonly DependencySerializationCheck $serialization,
        private readonly ConfigEntityExportCheck $export,
        private readonly PluginAnnotationContextCheck $context,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::Class_];
    }

    public function getRequirements(): array
    {
        // Mago ships a file's text once for all hooks, and other hooks
        // already ask for it.
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = $context->source;
        [$mentions, $candidates] = DeclaredClass::names($file, $context->node);
        $checks = [];
        if (array_key_exists(strtolower(HookMethods::ATTRIBUTE), $mentions)) {
            $checks = $this->hookChecks;
        }

        /** @var list<non-empty-string> $names */
        $names = array_keys($mentions);
        if (StorageTypes::namedLike($names) || StorageTypes::documentedLike($file->getText($context->node->span))) {
            $checks[] = $this->storage;
        }

        if (array_key_exists(strtolower(self::TRAIT), $mentions)) {
            $checks[] = $this->serialization;
        }

        $configAttribute = array_key_exists(strtolower(self::CONFIG_ENTITY_TYPE), $mentions);
        if ($configAttribute || $this->annotatedConfigEntity($candidates)) {
            $checks[] = $this->export;
        }

        if (ClassNames::anyIs($candidates, array_keys(($this->annotated)()->contextKeyed))) {
            $checks[] = $this->context;
        }

        if ($checks === []) {
            return;
        }

        $class = DeclaredClass::resolve($file, $context->node, $context->codebase, $candidates);
        if ($class === null || $class->kind !== ClassLikeKind::Class_) {
            return;
        }

        $facts = new ClassFacts($class, $context->codebase, $mentions);
        // Composing the trait itself is this hook's case; descendants of the
        // core bases that compose it are the descendant hook's, and a class
        // that only names the trait without using it is nobody's.
        if (
            in_array($this->serialization, $checks, strict: true)
            && (!$facts->composes(self::TRAIT) || $facts->extendsAny(DependencySerializationCheck::BASES))
        ) {
            $checks = self::without($checks, $this->serialization);
        }

        $reporter = new Reporter($context);
        foreach ($checks as $check) {
            $check->check($facts, $reporter);
        }
    }

    /**
     * An annotated config entity type carries no attribute to mention, so
     * the annotation scan's list says whether one of the candidates is one.
     *
     * @param list<non-empty-string> $candidates
     */
    private function annotatedConfigEntity(array $candidates): bool
    {
        $classes = [];
        foreach (($this->annotated)()->entityTypes as $definition) {
            if ($definition->kind !== EntityTypeKind::Config) {
                continue;
            }

            $classes[] = $definition->class;
        }

        return $classes !== [] && ClassNames::anyIs($candidates, $classes);
    }

    /**
     * @param list<MetadataCheck> $checks
     * @return list<MetadataCheck>
     */
    private static function without(array $checks, MetadataCheck $check): array
    {
        $kept = [];
        foreach ($checks as $candidate) {
            if ($candidate === $check) {
                continue;
            }

            $kept[] = $candidate;
        }

        return $kept;
    }
}
