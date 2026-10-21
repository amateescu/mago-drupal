<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer;

use amateescu\MagoDrupal\Analyzer\Config\ConfigFactoryProvider;
use amateescu\MagoDrupal\Analyzer\Config\ConfigGetProvider;
use amateescu\MagoDrupal\Analyzer\Config\ConfigStorageProvider;
use amateescu\MagoDrupal\Analyzer\Config\ConfigUnknownKeyHook;
use amateescu\MagoDrupal\Analyzer\Config\ConfigUnknownNameHook;
use amateescu\MagoDrupal\Analyzer\CoreTypes\ContainerInjectionProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\EntityIdListParameterProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\EntityIdProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\EventListProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\HandlerInstanceProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\InspectorAssertionProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\QueueItemProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\ScannedFilesProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\StubFiles;
use amateescu\MagoDrupal\Analyzer\CoreTypes\UninstallReasonsProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\UrlToStringProvider;
use amateescu\MagoDrupal\Analyzer\CoreTypes\ViewsQueryGroupProvider;
use amateescu\MagoDrupal\Analyzer\Deprecation\ImplicitTransactionCommitHook;
use amateescu\MagoDrupal\Analyzer\Entity\ConfigEntityExportCheck;
use amateescu\MagoDrupal\Analyzer\Entity\EntityAccessProvider;
use amateescu\MagoDrupal\Analyzer\Entity\EntityFieldProvider;
use amateescu\MagoDrupal\Analyzer\Entity\EntityKeyGuardFilter;
use amateescu\MagoDrupal\Analyzer\Entity\EntityKeyProvider;
use amateescu\MagoDrupal\Analyzer\Entity\EntityMagicPropertyFilter;
use amateescu\MagoDrupal\Analyzer\Entity\EntityQueryAccessCheckHook;
use amateescu\MagoDrupal\Analyzer\Entity\EntityQueryAssertionProvider;
use amateescu\MagoDrupal\Analyzer\Entity\EntityQueryProvider;
use amateescu\MagoDrupal\Analyzer\Entity\EntityRepositoryProvider;
use amateescu\MagoDrupal\Analyzer\Entity\EntityStorageProvider;
use amateescu\MagoDrupal\Analyzer\Entity\EntityTypeManagerProvider;
use amateescu\MagoDrupal\Analyzer\Entity\FieldItemPropertyProvider;
use amateescu\MagoDrupal\Analyzer\Entity\LanguageKeysProvider;
use amateescu\MagoDrupal\Analyzer\Entity\ListBuilderCacheabilityCheck;
use amateescu\MagoDrupal\Analyzer\Entity\ListBuilderOperationsProvider;
use amateescu\MagoDrupal\Analyzer\Entity\MachineNameKeysProvider;
use amateescu\MagoDrupal\Analyzer\Entity\ReferencedEntitiesProvider;
use amateescu\MagoDrupal\Analyzer\Entity\UnknownEntityTypeHook;
use amateescu\MagoDrupal\Analyzer\Injection\DependencySerializationCheck;
use amateescu\MagoDrupal\Analyzer\Injection\EntityStorageInjectionCheck;
use amateescu\MagoDrupal\Analyzer\Injection\GlobalDrupalCallHook;
use amateescu\MagoDrupal\Analyzer\Injection\LoggerFromFactoryHook;
use amateescu\MagoDrupal\Analyzer\Injection\TraitStorageHook;
use amateescu\MagoDrupal\Analyzer\Module\AnonymousInternalParentHook;
use amateescu\MagoDrupal\Analyzer\Module\DeprecatedHookCheck;
use amateescu\MagoDrupal\Analyzer\Module\EntityOperationCacheabilityCheck;
use amateescu\MagoDrupal\Analyzer\Module\FormAlterSignatureCheck;
use amateescu\MagoDrupal\Analyzer\Module\InternalParentHook;
use amateescu\MagoDrupal\Analyzer\Module\LoadIncludeHook;
use amateescu\MagoDrupal\Analyzer\Module\ProceduralHookHook;
use amateescu\MagoDrupal\Analyzer\Plugin\PluginAnnotationContextCheck;
use amateescu\MagoDrupal\Analyzer\Plugin\PluginDefinitionArrayFilter;
use amateescu\MagoDrupal\Analyzer\Plugin\PluginDefinitionProvider;
use amateescu\MagoDrupal\Analyzer\Plugin\PluginManagerCheck;
use amateescu\MagoDrupal\Analyzer\Plugin\PluginManagerProvider;
use amateescu\MagoDrupal\Analyzer\Plugin\TraitPluginDefinitionProvider;
use amateescu\MagoDrupal\Analyzer\Plugin\UnknownPluginHook;
use amateescu\MagoDrupal\Analyzer\Render\CacheableDependencyHook;
use amateescu\MagoDrupal\Analyzer\Render\ElementCallbackHook;
use amateescu\MagoDrupal\Analyzer\Render\FormArgumentsProvider;
use amateescu\MagoDrupal\Analyzer\Render\FormResponseReturnFilter;
use amateescu\MagoDrupal\Analyzer\Render\TrustedCallbackOverrideCheck;
use amateescu\MagoDrupal\Analyzer\Service\ClassResolverProvider;
use amateescu\MagoDrupal\Analyzer\Service\ContainerGetProvider;
use amateescu\MagoDrupal\Analyzer\Service\ContainerParameterProvider;
use amateescu\MagoDrupal\Analyzer\Service\DeprecatedServiceHook;
use amateescu\MagoDrupal\Analyzer\Service\ServiceArgumentsCheck;
use amateescu\MagoDrupal\Analyzer\Service\ServiceProviderScan;
use amateescu\MagoDrupal\Analyzer\Service\UnknownServiceHook;
use amateescu\MagoDrupal\Analyzer\Support\AnnotatedSourceScan;
use amateescu\MagoDrupal\Analyzer\Support\ClassMetadataHook;
use amateescu\MagoDrupal\Analyzer\Support\DescendantMetadataHook;
use amateescu\MagoDrupal\Analyzer\Support\MetadataCheck;
use amateescu\MagoDrupal\Analyzer\Test\BrowserTestThemeCheck;
use amateescu\MagoDrupal\Analyzer\Test\TestClassHook;
use amateescu\MagoDrupal\Analyzer\Traits\SelfReturnProvider;
use amateescu\MagoDrupal\Analyzer\Traits\TraitCallProvider;
use amateescu\MagoDrupal\Analyzer\Traits\TraitPropertyFilter;
use amateescu\MagoDrupal\Analyzer\Traits\TraitStaticCallHook;
use amateescu\MagoDrupal\Analyzer\Translation\UntranslatedMessageHook;
use amateescu\MagoDrupal\Internal\Cache\DiskCache;
use amateescu\MagoDrupal\Internal\Cache\Indexes;
use amateescu\MagoDrupal\Internal\Callback\TrustedCallbacks;
use amateescu\MagoDrupal\Internal\Declaration\ClassTargets;
use amateescu\MagoDrupal\Internal\Deprecation\DeprecationTarget;
use amateescu\MagoDrupal\Internal\Ignore\PhpcsCommentFilter;
use amateescu\MagoDrupal\Internal\Traits\TraitRoots;
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
     * @param string|null $root Drupal document root, absolute or relative to
     *   the worker's cwd. Discovered from the cwd when null.
     * @param DeprecationTarget|null $deprecations The Drupal major whose
     *   removals are reported; every deprecation when null.
     * @param bool $phpcsIgnores Lets a phpcs suppression comment drop the
     *   issues of the checks that port the Coder codes it ignores.
     */
    public function __construct(
        ?string $root = null,
        ?DeprecationTarget $deprecations = null,
        private readonly bool $phpcsIgnores = false,
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

        foreach ([
            new FormResponseReturnFilter(),
            new EntityMagicPropertyFilter(),
            new EntityKeyGuardFilter(),
            new PluginDefinitionArrayFilter(),
            new TraitPropertyFilter($traitRoots),
        ] as $filter) {
            $registry->registerIssueFilterHook($filter);
        }

        $indexes = $this->indexes;
        $this->registerServiceHooks($registry);

        $this->registerEntityHooks($registry);
        // Ahead of the generic trait call provider, which would answer first.
        $registry->registerMethodReturnTypeProvider(new TraitPluginDefinitionProvider());
        $registry->registerMethodReturnTypeProvider(new TraitCallProvider($traitRoots));
        $registry->registerNodeAnalysisHook(new TraitStaticCallHook($traitRoots));
        $registry->registerMethodReturnTypeProvider(new SelfReturnProvider());
        $this->registerCoreTypes($registry);

        $this->registerConfigHooks($registry);

        $registry->registerMethodReturnTypeProvider(new PluginManagerProvider($indexes->plugins(...)));
        $registry->registerMethodReturnTypeProvider(new PluginDefinitionProvider());
        $registry->registerMethodCallAnalysisHook(new UnknownPluginHook($indexes->plugins(...)));

        $this->registerClassChecks($registry, $traitRoots);
        // The checks that port a Coder sniff honour phpcs comments when the
        // worker reads them.
        $phpcsIgnores = $this->phpcsIgnores;
        $phpcs = static fn(string $code): ?PhpcsCommentFilter => $phpcsIgnores
            ? PhpcsCommentFilter::for('drupal/' . $code)
            : null;
        $registry->registerMethodCallAnalysisHook(
            new GlobalDrupalCallHook($indexes->services(...), phpcs: $phpcs(GlobalDrupalCallHook::CODE)),
        );
        $registry->registerMethodCallAnalysisHook(
            new GlobalDrupalCallHook(
                $indexes->services(...),
                entityLoads: true,
                phpcs: $phpcs(GlobalDrupalCallHook::ENTITY_LOAD_CODE),
            ),
        );
        $untranslated = $phpcs(UntranslatedMessageHook::CODE);
        $registry->registerMethodCallAnalysisHook(new UntranslatedMessageHook(
            UntranslatedMessageHook::FORM_ERRORS,
            $untranslated,
        ));
        $registry->registerMethodCallAnalysisHook(new UntranslatedMessageHook(
            UntranslatedMessageHook::MESSAGES,
            $untranslated,
        ));

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
        $composers = $this->indexes->traitComposers(DependencySerializationCheck::TRAIT);
        $serialization = new DependencySerializationCheck($composers);
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
        $registry->registerClassLikeAnalysisHook(
            new DescendantMetadataHook(
                ListBuilderCacheabilityCheck::ANCESTORS,
                new ListBuilderCacheabilityCheck($this->indexes->coreVersion(...)),
            ),
        );

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
        $annotated = AnnotatedSourceScan::targets($indexes->annotatedFiles());
        if ($annotated !== []) {
            $registry->registerCodebaseScanHook(
                new AnnotatedSourceScan($annotated, $indexes->setAnnotatedSources(...)),
            );
        }
        $registry->registerMethodReturnTypeProvider(new ContainerGetProvider($services));
        $registry->registerMethodReturnTypeProvider(new ContainerParameterProvider($indexes->parameters(...)));
        $registry->registerMethodReturnTypeProvider(new ClassResolverProvider($services));
        $registry->registerMethodCallAnalysisHook(new DeprecatedServiceHook($services, $this->deprecations));
        $registry->registerMethodCallAnalysisHook(new UnknownServiceHook($services, $this->indexes->modules(...)));
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
        $registry->registerMethodAssertionProvider(new InspectorAssertionProvider());
        $registry->registerMethodReturnTypeProvider(new QueueItemProvider());
        $registry->registerMethodReturnTypeProvider(new ScannedFilesProvider());
        $registry->registerMethodReturnTypeProvider(new UninstallReasonsProvider());
        $registry->registerMethodReturnTypeProvider(new UrlToStringProvider());
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
        $registry->registerPropertyTypeProvider(new EntityFieldProvider($this->indexes->fieldNames(...)));
        $registry->registerPropertyTypeProvider(new FieldItemPropertyProvider());
        $registry->registerMethodReturnTypeProvider(new ReferencedEntitiesProvider());
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
     * any doesn't need the hook at all.
     */
    private function registerInternalParentHook(PluginRegistry $registry): void
    {
        $internal = ClassTargets::accepted($this->indexes->internalClasses()->names());
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
}
