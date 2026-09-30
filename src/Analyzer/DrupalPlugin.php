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
use amateescu\MagoDrupal\Analyzer\Checks\MetadataCheck;
use amateescu\MagoDrupal\Analyzer\Checks\PluginAnnotationContextCheck;
use amateescu\MagoDrupal\Analyzer\Checks\PluginManagerCheck;
use amateescu\MagoDrupal\Analyzer\Checks\ServiceArgumentsCheck;
use amateescu\MagoDrupal\Analyzer\Checks\TrustedCallbackOverrideCheck;
use amateescu\MagoDrupal\Analyzer\Hooks\AnonymousInternalParentHook;
use amateescu\MagoDrupal\Analyzer\Hooks\CacheableDependencyHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ClassMetadataHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ConfigUnknownKeyHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ConfigUnknownNameHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DeprecatedServiceHook;
use amateescu\MagoDrupal\Analyzer\Hooks\DescendantMetadataHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ElementCallbackHook;
use amateescu\MagoDrupal\Analyzer\Hooks\EntityMagicPropertyFilter;
use amateescu\MagoDrupal\Analyzer\Hooks\EntityQueryAccessCheckHook;
use amateescu\MagoDrupal\Analyzer\Hooks\FormResponseReturnFilter;
use amateescu\MagoDrupal\Analyzer\Hooks\GlobalDrupalCallHook;
use amateescu\MagoDrupal\Analyzer\Hooks\ImplicitTransactionCommitHook;
use amateescu\MagoDrupal\Analyzer\Hooks\InternalParentHook;
use amateescu\MagoDrupal\Analyzer\Hooks\LoadIncludeHook;
use amateescu\MagoDrupal\Analyzer\Hooks\LoggerFromFactoryHook;
use amateescu\MagoDrupal\Analyzer\Hooks\PluginDefinitionArrayFilter;
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
use amateescu\MagoDrupal\Analyzer\Providers\ContainerInjectionProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ContainerParameterProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityAccessProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityFieldProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityIdListParameterProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityIdProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityKeyProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityQueryAssertionProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityQueryProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityRepositoryProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityStorageProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EntityTypeManagerProvider;
use amateescu\MagoDrupal\Analyzer\Providers\EventListProvider;
use amateescu\MagoDrupal\Analyzer\Providers\FieldItemPropertyProvider;
use amateescu\MagoDrupal\Analyzer\Providers\FormArgumentsProvider;
use amateescu\MagoDrupal\Analyzer\Providers\HandlerInstanceProvider;
use amateescu\MagoDrupal\Analyzer\Providers\LanguageKeysProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ListBuilderOperationsProvider;
use amateescu\MagoDrupal\Analyzer\Providers\MachineNameKeysProvider;
use amateescu\MagoDrupal\Analyzer\Providers\PluginDefinitionProvider;
use amateescu\MagoDrupal\Analyzer\Providers\PluginManagerProvider;
use amateescu\MagoDrupal\Analyzer\Providers\QueueItemProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ScannedFilesProvider;
use amateescu\MagoDrupal\Analyzer\Providers\SelfReturnProvider;
use amateescu\MagoDrupal\Analyzer\Providers\TraitCallProvider;
use amateescu\MagoDrupal\Analyzer\Providers\TraitPluginDefinitionProvider;
use amateescu\MagoDrupal\Analyzer\Providers\UninstallReasonsProvider;
use amateescu\MagoDrupal\Analyzer\Providers\ViewsQueryGroupProvider;
use amateescu\MagoDrupal\Internal\ClassTargets;
use amateescu\MagoDrupal\Internal\DeprecationTarget;
use amateescu\MagoDrupal\Internal\DiskCache;
use amateescu\MagoDrupal\Internal\Indexes;
use amateescu\MagoDrupal\Internal\TraitRoots;
use amateescu\MagoDrupal\Internal\TrustedCallbacks;
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

    private readonly DeprecationTarget $deprecations;

    /**
     * @param bool $core Enables rules that only apply to Drupal core itself.
     * @param string|null $root Drupal document root, absolute or relative to
     *   the worker's cwd. Discovered from the cwd when null.
     * @param DeprecationTarget|null $deprecations The Drupal major whose
     *   removals are reported; every deprecation when null.
     */
    public function __construct(
        private readonly bool $core = false,
        ?string $root = null,
        ?DeprecationTarget $deprecations = null,
    ) {
        $this->indexes = new Indexes($root, DiskCache::fromEnvironment());
        $this->deprecations = $deprecations ?? DeprecationTarget::all();
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
        $registry->registerInitializationHook(new StubFiles(coreVersion: $this->indexes->installedCoreVersion(...)));
        $traitRoots = new TraitRoots();
        DeprecationHooks::register($registry, $this->indexes, $this->deprecations);

        $registry->registerIssueFilterHook(new FormResponseReturnFilter());
        $registry->registerIssueFilterHook(new EntityMagicPropertyFilter());
        $registry->registerIssueFilterHook(new PluginDefinitionArrayFilter());
        $registry->registerIssueFilterHook(new TraitPropertyFilter($traitRoots));

        $indexes = $this->indexes;
        $this->registerServiceHooks($registry);

        $this->registerEntityHooks($registry);
        // Ahead of the generic trait call provider, which would answer first.
        $registry->registerMethodReturnTypeProvider(new TraitPluginDefinitionProvider());
        $registry->registerMethodReturnTypeProvider(new TraitCallProvider($traitRoots));
        $registry->registerMethodReturnTypeProvider(new SelfReturnProvider());
        $this->registerCoreTypes($registry);

        $this->registerConfigHooks($registry);

        $registry->registerMethodReturnTypeProvider(new PluginManagerProvider($indexes->plugins(...)));
        $registry->registerMethodReturnTypeProvider(new PluginDefinitionProvider());
        $registry->registerMethodCallAnalysisHook(new UnknownPluginHook($indexes->plugins(...)));

        $this->registerClassChecks($registry, $traitRoots);
        $registry->registerMethodCallAnalysisHook(new GlobalDrupalCallHook());
        $registry->registerMethodCallAnalysisHook(new LoggerFromFactoryHook());
        $registry->registerMethodCallAnalysisHook(new ImplicitTransactionCommitHook());
        $registry->registerMethodCallAnalysisHook(new CacheableDependencyHook(CacheableDependencyHook::REFINABLE));
        $registry->registerMethodCallAnalysisHook(new CacheableDependencyHook(CacheableDependencyHook::RENDERER));

        $registry->registerMethodCallAnalysisHook(new LoadIncludeHook($indexes->modules(...)));
        $this->registerInternalParentHook($registry);
    }

    /**
     * Class-level rules read metadata, never a subtree. Ancestry comes from
     * the host's class-like targets and hook facts from the api.php files on
     * disk.
     */
    private function registerClassChecks(PluginRegistry $registry, TraitRoots $traitRoots): void
    {
        $hooks = $this->indexes->hookFunctions(...);
        $trusted = new TrustedCallbacks();
        $storage = new EntityStorageInjectionCheck($traitRoots);
        $serialization =
            new DependencySerializationCheck($this->indexes->traitComposers(DependencySerializationCheck::TRAIT));
        $registry->registerNodeAnalysisHook(
            new ClassMetadataHook(
                $this->indexes->annotated(...),
                [
                    new DeprecatedHookCheck($hooks, $this->deprecations),
                    new FormAlterSignatureCheck(),
                    new EntityOperationCacheabilityCheck($hooks),
                ],
                $storage,
                $serialization,
                new ConfigEntityExportCheck($this->indexes->entityTypes(...)),
                new PluginAnnotationContextCheck($this->indexes->annotated(...)),
                new ServiceArgumentsCheck($this->indexes->wiring(...), $this->indexes->alteredServiceIds(...)),
            ),
        );
        $registry->registerNodeAnalysisHook(
            new ElementCallbackHook($this->indexes->modules(...), $this->indexes->services(...), $trusted),
        );
        $registry->registerNodeAnalysisHook(new TraitStorageHook($storage));
        $registry->registerNodeAnalysisHook(new ProceduralHookHook($hooks, $this->deprecations));
        $registry->registerClassLikeAnalysisHook(new TestClassHook());
        $registry->registerClassLikeAnalysisHook(
            new DescendantMetadataHook(
                BrowserTestThemeCheck::ANCESTORS,
                new BrowserTestThemeCheck($this->indexes->profileThemes(...)),
            ),
        );
        $registry->registerMethodReturnTypeProvider(
            new ListBuilderOperationsProvider($this->indexes->coreVersion(...)),
        );
        // Core keeps the parameter commented out in its own list builders, so
        // the check is for contrib.
        if (!$this->core) {
            $registry->registerClassLikeAnalysisHook(
                new DescendantMetadataHook(
                    ListBuilderCacheabilityCheck::ANCESTORS,
                    new ListBuilderCacheabilityCheck($this->indexes->coreVersion(...)),
                ),
            );
        }

        $this->registerDescendantCheck($registry, $serialization->bases(), $serialization);
        $overridden = $this->indexes->trustedCallbackClasses();
        $this->registerDescendantCheck(
            $registry,
            TrustedCallbackOverrideCheck::ancestors($overridden, $this->indexes->traitComposers(...)),
            new TrustedCallbackOverrideCheck($trusted, $overridden->methods),
        );
        $registry->registerClassLikeAnalysisHook(
            new DescendantMetadataHook(
                PluginManagerCheck::ANCESTORS,
                new PluginManagerCheck($this->indexes->wiring(...)),
            ),
        );
    }

    /**
     * The container providers typing services and parameters from the
     * services files and providers, and the hooks reporting deprecated and
     * unknown ids.
     */
    private function registerServiceHooks(PluginRegistry $registry): void
    {
        $indexes = $this->indexes;
        $services = $indexes->services(...);
        $registry->registerCodebaseScanHook(new ServiceProviderScan($indexes->reset(...), $indexes->setProvided(...)));
        $registry->registerMethodReturnTypeProvider(new ContainerGetProvider($services));
        $registry->registerMethodReturnTypeProvider(new ContainerParameterProvider($indexes->parameters(...)));
        $registry->registerMethodReturnTypeProvider(new ClassResolverProvider($services));
        $registry->registerMethodCallAnalysisHook(new DeprecatedServiceHook($services, $this->deprecations));
        $registry->registerMethodCallAnalysisHook(new UnknownServiceHook($services));
    }

    /**
     * The config providers typing reads from the schema, and the hooks
     * reporting unknown config names and keys.
     */
    private function registerConfigHooks(PluginRegistry $registry): void
    {
        $schema = $this->indexes->configSchema(...);
        $registry->registerMethodReturnTypeProvider(new ConfigFactoryProvider());
        $registry->registerMethodReturnTypeProvider(new ConfigGetProvider($schema));
        $registry->registerMethodReturnTypeProvider(new ConfigStorageProvider($schema));
        $registry->registerMethodCallAnalysisHook(new ConfigUnknownKeyHook($schema));
        $registry->registerMethodCallAnalysisHook(new ConfigUnknownNameHook($schema, $this->indexes->modules(...)));
    }

    /**
     * Providers that type core's return values and parameters the way the
     * code behaves where its docblocks say less or something else.
     */
    private function registerCoreTypes(PluginRegistry $registry): void
    {
        $registry->registerMethodReturnTypeProvider(new LanguageKeysProvider());
        $registry->registerMethodReturnTypeProvider(new MachineNameKeysProvider());
        $registry->registerMethodReturnTypeProvider(new ContainerInjectionProvider());
        $registry->registerMethodReturnTypeProvider(new EntityIdListParameterProvider());
        $registry->registerMethodReturnTypeProvider(new EventListProvider());
        $registry->registerMethodReturnTypeProvider(new FormArgumentsProvider());
        $registry->registerMethodReturnTypeProvider(new QueueItemProvider());
        $registry->registerMethodReturnTypeProvider(new ScannedFilesProvider());
        $registry->registerMethodReturnTypeProvider(new UninstallReasonsProvider());
        $registry->registerMethodReturnTypeProvider(new ViewsQueryGroupProvider());
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
        $registry->registerMethodReturnTypeProvider(new HandlerInstanceProvider());
        $registry->registerMethodReturnTypeProvider(new EntityIdProvider($entityTypes));
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
        $registry->registerMethodReturnTypeProvider(new EntityKeyProvider());
        $registry->registerPropertyTypeProvider(new EntityFieldProvider());
        $registry->registerPropertyTypeProvider(new FieldItemPropertyProvider());
    }

    /**
     * Registers a check for the descendants of classes read off disk.
     *
     * @param list<string> $ancestors
     */
    private function registerDescendantCheck(PluginRegistry $registry, array $ancestors, MetadataCheck $check): void
    {
        $accepted = ClassTargets::accepted($ancestors);
        if ($accepted !== []) {
            $registry->registerClassLikeAnalysisHook(new DescendantMetadataHook($accepted, $check));
        }
    }

    /**
     * The internal classes are read off the disk at registration, since the
     * host wants the ancestors before the first request; a workspace without
     * any needs no hook at all. Core's own phpstan configuration ignores the
     * rule, so `--core` leaves it out too.
     */
    private function registerInternalParentHook(PluginRegistry $registry): void
    {
        $internal = ClassTargets::accepted($this->core ? [] : $this->indexes->internalClasses()->names());
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
