<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer;

use amateescu\MagoDrupal\Analyzer\Checks\BrowserTestThemeCheck;
use amateescu\MagoDrupal\Analyzer\Checks\ConfigEntityExportCheck;
use amateescu\MagoDrupal\Analyzer\Checks\DependencySerializationCheck;
use amateescu\MagoDrupal\Analyzer\Checks\DeprecatedHookCheck;
use amateescu\MagoDrupal\Analyzer\Checks\EntityOperationCacheabilityCheck;
use amateescu\MagoDrupal\Analyzer\Checks\EntityStorageInjectionCheck;
use amateescu\MagoDrupal\Analyzer\Checks\FormAlterSignatureCheck;
use amateescu\MagoDrupal\Analyzer\Checks\ListBuilderCacheabilityCheck;
use amateescu\MagoDrupal\Analyzer\Checks\PluginAnnotationContextCheck;
use amateescu\MagoDrupal\Analyzer\Hooks\AnnotationScan;
use amateescu\MagoDrupal\Analyzer\Hooks\AnonymousInternalParentHook;
use amateescu\MagoDrupal\Analyzer\Hooks\CacheableDependencyHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ClassMetadataHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ConfigUnknownKeyHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ConfigUnknownNameHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedOriginalHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedServiceHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecationScopeFilter;
use amateescu\MagoDrupal\Analyzer\Hooks\DescendantMetadataHook;
use amateescu\MagoDrupal\Analyzer\Hooks\EntityQueryAccessCheckHook;
use amateescu\MagoDrupal\Analyzer\Hooks\FormResponseReturnFilter;
use amateescu\MagoDrupal\Analyzer\Hooks\GlobalDrupalCallHook;
use amateescu\MagoDrupal\Analyzer\Hooks\InternalParentHook;
use amateescu\MagoDrupal\Analyzer\Hooks\LoadIncludeHook;
use amateescu\MagoDrupal\Analyzer\Hooks\LoggerFromFactoryHook;
use amateescu\MagoDrupal\Analyzer\Hooks\PluginManagerAuditHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ProceduralHookHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ServiceProviderScan;
use amateescu\MagoDrupal\Analyzer\Hooks\StubFiles;
use amateescu\MagoDrupal\Analyzer\Hooks\TestClassHook;
use amateescu\MagoDrupal\Analyzer\Hooks\TraitPropertyFilter;
use amateescu\MagoDrupal\Analyzer\Hooks\TraitStorageHook;
use amateescu\MagoDrupal\Analyzer\Hooks\UnknownEntityTypeHook;
use amateescu\MagoDrupal\Analyzer\Hooks\UnknownPluginHook;
use amateescu\MagoDrupal\Analyzer\Hooks\UnknownServiceHook;
use amateescu\MagoDrupal\Analyzer\Providers\ClassResolverProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ConfigFactoryProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ConfigGetProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ConfigStorageProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ContainerGetProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityAccessProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityFieldProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityQueryAssertionProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityQueryProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityRepositoryProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityStorageProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityTypeManagerProvider;
use amateescu\MagoDrupal\Analyzer\Providers\FieldItemPropertyProvider;
use amateescu\MagoDrupal\Analyzer\Providers\LanguageKeysProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ListBuilderOperationsProvider;
use amateescu\MagoDrupal\Analyzer\Providers\PluginManagerProvider;
use amateescu\MagoDrupal\Analyzer\Providers\SelfReturnProvider;
use amateescu\MagoDrupal\Analyzer\Providers\TraitCallProvider;
use amateescu\MagoDrupal\Internal\DiskCache;
use amateescu\MagoDrupal\Internal\Indexes;
use amateescu\MagoDrupal\Internal\TraitRoots;
use InvalidArgumentException;
use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Analyzer\ClassTarget;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginDefinition;
use Mago\Sdk\Analyzer\PluginRegistry;

use function strtolower;

/**
 * Gives the analyzer the facts about Drupal's runtime wiring.
 *
 * @internal
 */
final class DrupalPlugin implements Plugin
{
    private readonly Indexes $indexes;

    /**
     * @param bool $core Enables rules that only apply to Drupal core itself.
     * @param string|null $root Drupal document root, absolute or relative to
     *   the worker's cwd. Discovered from the cwd when null.
     */
    public function __construct(
        private readonly bool $core = false,
        ?string $root = null,
    ) {
        $this->indexes = new Indexes($root, DiskCache::fromEnvironment());
    }

    public function getDefinition(): PluginDefinition
    {
        return new PluginDefinition(
            identifier: 'drupal',
            name: 'Drupal',
            description: 'Resolves Drupal services, entity storage, plugins and configuration.',
        );
    }

    public function register(PluginRegistry $registry): void
    {
        $registry->enableProviderMemoization();
        $registry->registerInitializationHook(new StubFiles());
        $traitRoots = new TraitRoots();
        $registry->registerIssueFilterHook(new DeprecationScopeFilter());
        $registry->registerIssueFilterHook(new FormResponseReturnFilter());
        $registry->registerIssueFilterHook(new TraitPropertyFilter($traitRoots));

        $indexes = $this->indexes;
        $registry->registerCodebaseScanHook(new ServiceProviderScan($indexes->reset(...), $indexes->setProvided(...)));
        $registry->registerCodebaseScanHook(new AnnotationScan($indexes->setAnnotated(...)));
        $registry->registerMethodReturnTypeProvider(new ContainerGetProvider($indexes->services(...)));
        $registry->registerMethodReturnTypeProvider(new ClassResolverProvider($indexes->services(...)));
        $registry->registerMethodCallAnalysisHook(new DeprecatedServiceHook($indexes->services(...)));
        $registry->registerNodeAnalysisHook(new DeprecatedOriginalHook());
        $registry->registerMethodCallAnalysisHook(new UnknownServiceHook($indexes->services(...)));

        $this->registerEntityHooks($registry);
        $registry->registerMethodReturnTypeProvider(new TraitCallProvider($traitRoots));
        $registry->registerMethodReturnTypeProvider(new SelfReturnProvider());
        $registry->registerMethodReturnTypeProvider(new LanguageKeysProvider());

        $registry->registerMethodReturnTypeProvider(new ConfigFactoryProvider());
        $registry->registerMethodReturnTypeProvider(new ConfigGetProvider($indexes->configSchema(...)));
        $registry->registerMethodReturnTypeProvider(new ConfigStorageProvider($indexes->configSchema(...)));
        $registry->registerMethodCallAnalysisHook(new ConfigUnknownKeyHook($indexes->configSchema(...)));
        $registry->registerMethodCallAnalysisHook(
            new ConfigUnknownNameHook($indexes->configSchema(...), $indexes->modules(...)),
        );

        $registry->registerMethodReturnTypeProvider(new PluginManagerProvider($indexes->plugins(...)));
        $registry->registerMethodCallAnalysisHook(new UnknownPluginHook($indexes->plugins(...)));

        // Hook implementations are called by the module handler, never from
        // PHP the analyzer can see.
        $registry->registerAttributedEntryPoint(ClassTarget::any(), 'Drupal\Core\Hook\Attribute\Hook');

        // Class-level rules read metadata, never a subtree. Ancestry comes
        // from the host's class-like targets and hook facts from the api.php
        // files on disk.
        $hooks = $indexes->hookFunctions(...);
        $serialization = new DependencySerializationCheck();
        $storage = new EntityStorageInjectionCheck($traitRoots);
        $registry->registerNodeAnalysisHook(
            new ClassMetadataHook(
                $indexes->annotated(...),
                [
                    new DeprecatedHookCheck($hooks),
                    new FormAlterSignatureCheck(),
                    new EntityOperationCacheabilityCheck($hooks),
                ],
                $storage,
                $serialization,
                new ConfigEntityExportCheck($indexes->entityTypes(...)),
                new PluginAnnotationContextCheck($indexes->annotated(...)),
            ),
        );
        $registry->registerNodeAnalysisHook(new TraitStorageHook($storage));
        $registry->registerNodeAnalysisHook(new ProceduralHookHook($hooks));
        $registry->registerClassLikeAnalysisHook(new TestClassHook());
        $registry->registerClassLikeAnalysisHook(
            new DescendantMetadataHook(BrowserTestThemeCheck::ANCESTORS, new BrowserTestThemeCheck()),
        );
        $registry->registerMethodReturnTypeProvider(new ListBuilderOperationsProvider($indexes->coreVersion(...)));
        // Core keeps the parameter commented out in its own list builders, so
        // the check is for contrib.
        if (!$this->core) {
            $registry->registerClassLikeAnalysisHook(
                new DescendantMetadataHook(
                    ListBuilderCacheabilityCheck::ANCESTORS,
                    new ListBuilderCacheabilityCheck($indexes->coreVersion(...)),
                ),
            );
        }

        $registry->registerClassLikeAnalysisHook(new DescendantMetadataHook(
            DependencySerializationCheck::BASES,
            $serialization,
        ));
        $registry->registerMethodCallAnalysisHook(new GlobalDrupalCallHook());
        $registry->registerMethodCallAnalysisHook(new LoggerFromFactoryHook());
        $registry->registerMethodCallAnalysisHook(new CacheableDependencyHook(CacheableDependencyHook::REFINABLE));
        $registry->registerMethodCallAnalysisHook(new CacheableDependencyHook(CacheableDependencyHook::RENDERER));

        $registry->registerAfterAnalysisHook(new PluginManagerAuditHook());
        $registry->registerMethodCallAnalysisHook(new LoadIncludeHook($indexes->modules(...)));
        $this->registerInternalParentHook($registry);
    }

    /**
     * The entity type manager, storage, repository, query and field
     * providers, and the hooks reporting unknown entity types and unchecked
     * queries.
     */
    private function registerEntityHooks(PluginRegistry $registry): void
    {
        $entityTypes = $this->indexes->entityTypes(...);
        $registry->registerMethodReturnTypeProvider(new EntityTypeManagerProvider($entityTypes));
        $registry->registerMethodReturnTypeProvider(new EntityStorageProvider($entityTypes));
        $registry->registerMethodReturnTypeProvider(new EntityRepositoryProvider($entityTypes));
        $registry->registerMethodReturnTypeProvider(new EntityQueryProvider($entityTypes));
        $registry->registerMethodCallAnalysisHook(new UnknownEntityTypeHook(
            $entityTypes,
            UnknownEntityTypeHook::SINGLE,
        ));
        $registry->registerMethodCallAnalysisHook(new UnknownEntityTypeHook(
            $entityTypes,
            UnknownEntityTypeHook::PAIRED,
        ));
        $registry->registerMethodAssertionProvider(new EntityQueryAssertionProvider());
        $registry->registerMethodCallAnalysisHook(new EntityQueryAccessCheckHook());
        $registry->registerMethodReturnTypeProvider(new EntityAccessProvider());
        $registry->registerPropertyTypeProvider(new EntityFieldProvider());
        $registry->registerPropertyTypeProvider(new FieldItemPropertyProvider());
    }

    /**
     * The internal classes are read off the disk at registration, since the
     * host wants the ancestors before the first request; a workspace without
     * any needs no hook at all. Core's own phpstan configuration ignores the
     * rule, so `--core` leaves it out too.
     */
    private function registerInternalParentHook(PluginRegistry $registry): void
    {
        $internal = [];
        foreach ($this->core ? [] : $this->indexes->internalClasses()->names() as $class) {
            // The names come off a regex over user code. One the SDK rejects,
            // such as a namespace segment named `Enum`, must not fail the
            // whole registration.
            try {
                ClassLikeTarget::descendantsOf($class);
            } catch (InvalidArgumentException) {
                continue;
            }

            $internal[] = $class;
        }

        if ($internal === []) {
            return;
        }

        $registry->registerClassLikeAnalysisHook(new InternalParentHook($internal));
        $lowercased = [];
        foreach ($internal as $class) {
            $lowercased[strtolower($class)] = true;
        }

        $registry->registerNodeAnalysisHook(new AnonymousInternalParentHook($lowercased));
    }

    /**
     * Whether the rules that apply only to Drupal core are enabled.
     */
    public function isCore(): bool
    {
        return $this->core;
    }
}
