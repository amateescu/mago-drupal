<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Revolt\EventLoop;
use Revolt\EventLoop\Suspension;
use Symfony\Component\Yaml\Tag\TaggedValue;

use function array_key_exists;
use function array_unique;
use function array_values;
use function getcwd;
use function implode;
use function sha1;

/**
 * Owns every index for one analysis run and rebuilds them for the next.
 *
 * Disk-backed indexes (services YAML, config schema, extension directories,
 * hook documentation) are read on first use and kept between runs by the disk
 * cache. Metadata-backed ones (entity types, plugins) are built from the
 * frozen codebase on the first provider request; one worker per run builds
 * and the others load its result through the cache. Every accessor takes the
 * codebase of the request asking, and starts over when that codebase belongs
 * to a new analysis (see follow()). The service provider scan's result
 * survives that: the scan replaces its own whenever it runs.
 *
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:too-many-properties
 */
final class Indexes
{
    private ?DrupalRoot $root = null;

    /**
     * @var array<non-empty-string, Definition>|null
     */
    private ?array $yaml = null;

    /**
     * Raw definitions from the `*ServiceProvider.php` files of the last scan.
     *
     * @var array<non-empty-string, Definition>
     */
    private array $provided = [];

    private ?ServiceIndex $services = null;

    private ?EntityTypeIndex $entityTypes = null;

    private ?PluginIndex $plugins = null;

    private ?ConfigSchema $configSchema = null;

    private ?HookFunctions $hookFunctions = null;

    /**
     * The analysis generation the indexes were built for, null before the
     * first request.
     */
    private ?int $generation = null;

    /**
     * @var array<string, bool>|null
     */
    private ?array $profileThemes = null;

    /**
     * Fibers waiting for a metadata-backed build in progress, by index name.
     *
     * @var array<string, list<Suspension<null>>>
     */
    private array $waiting = [];

    /**
     * @param string|null $rootPath Drupal document root, absolute or relative
     *   to the worker's cwd. Discovered from the cwd when null.
     */
    public function __construct(
        private readonly ?string $rootPath = null,
        private readonly ?DiskCache $cache = null,
    ) {}

    /**
     * Drops every index and the provider registrations of the last scan. The
     * service provider scan calls it on its first batch and publishes the new
     * registrations on its last.
     */
    public function reset(): void
    {
        $this->provided = [];
        $this->forget();
    }

    /**
     * Takes the service registrations the scan hook found; they win over YAML,
     * the order the container compiler uses.
     *
     * @param array<non-empty-string, Definition> $definitions
     */
    public function setProvided(array $definitions): void
    {
        $this->provided = $definitions;
        $this->services = null;
    }

    public function services(Codebase $codebase): ServiceIndex
    {
        $this->follow($codebase);

        // Provider ids read off disk have no class, so YAML wins over them and
        // the scanned providers win over YAML, without an id-only entry ever
        // erasing a class.

        return $this->services ??= ServiceIndex::fromDefinitions(ServiceDefinitions::merge(
            ServiceDefinitions::merge($this->root()->providerIds(), $this->yaml()),
            $this->provided,
        ));
    }

    /**
     * The service definitions of every services file under the root.
     *
     * @return array<non-empty-string, Definition>
     */
    private function yaml(): array
    {
        if ($this->yaml === null) {
            $files = $this->root()->serviceFiles();
            $this->yaml = $this->root()->cached(
                'services',
                [...$files, ...ServiceModuleInfo::infoFiles($files)],
                static fn(): array => ServiceYaml::load($files),
                [
                    TaggedValue::class,
                ],
            );
        }

        return $this->yaml;
    }

    /**
     * Entity types and plugins declared with a legacy annotation under the
     * root, analyzed or not.
     */
    public function annotated(): AnnotatedDeclarations
    {
        return $this->root()->annotatedDeclarations();
    }

    public function entityTypes(Codebase $codebase): EntityTypeIndex
    {
        $this->follow($codebase);
        $generation = $this->generation;
        $built = $this->once('entityTypes', fn(): bool => $this->entityTypes !== null, function () use (
            $codebase,
            $generation,
        ): EntityTypeIndex {
            $index = $this->buildEntityTypes($codebase);
            // A newer analysis may have started while this one built.
            if ($this->generation === $generation) {
                $this->entityTypes = $index;
            }

            return $index;
        });

        return $built ?? $this->entityTypes ?? $this->buildEntityTypes($codebase);
    }

    public function plugins(Codebase $codebase): PluginIndex
    {
        $this->follow($codebase);
        $generation = $this->generation;
        $built = $this->once('plugins', fn(): bool => $this->plugins !== null, function () use (
            $codebase,
            $generation,
        ): PluginIndex {
            $index = $this->buildPlugins($codebase);
            // A newer analysis may have started while this one built.
            if ($this->generation === $generation) {
                $this->plugins = $index;
            }

            return $index;
        });

        return $built ?? $this->plugins ?? $this->buildPlugins($codebase);
    }

    public function configSchema(Codebase $codebase): ConfigSchema
    {
        $this->follow($codebase);

        return $this->schema();
    }

    private function schema(): ConfigSchema
    {
        if ($this->configSchema === null) {
            $files = $this->root()->schemaFiles();
            $this->configSchema = ConfigSchema::fromDefinitions($this->root()->cached(
                'schema',
                $files,
                static fn(): array => ConfigSchema::fromFiles($files)->all(),
            ));
        }

        return $this->configSchema;
    }

    /**
     * Module machine name to directory.
     *
     * @return array<string, string>
     */
    public function modules(Codebase $codebase): array
    {
        $this->follow($codebase);

        return $this->root()->modules();
    }

    public function coreVersion(Codebase $codebase): ?string
    {
        $this->follow($codebase);

        return $this->root()->coreVersion();
    }

    /**
     * The core version, for a caller that runs before any analysis, such as
     * the stub files at initialization.
     */
    public function installedCoreVersion(): ?string
    {
        return $this->root()->coreVersion();
    }

    /**
     * Hook documentation facts, read from the `*.api.php` files under the root.
     */
    public function hookFunctions(Codebase $codebase): HookFunctions
    {
        $this->follow($codebase);

        return $this->hooks();
    }

    private function hooks(): HookFunctions
    {
        if ($this->hookFunctions === null) {
            $files = $this->root()->apiFiles();
            $this->hookFunctions = $this->root()->cached(
                'hooks',
                $files,
                static fn(): HookFunctions => HookFunctions::fromApiFiles($files),
                [HookFunctions::class],
            );
        }

        return $this->hookFunctions;
    }

    /**
     * Install profiles under the root, with whether each ships a theme.
     *
     * @return array<string, bool>
     */
    public function profileThemes(): array
    {
        return $this->profileThemes ??= $this->root()->profileThemes();
    }

    /**
     * Classes marked `@internal` under the root, for the internal-parent
     * check. Read once at registration, so nothing is kept here.
     */
    public function internalClasses(): InternalClasses
    {
        return $this->root()->internalClasses();
    }

    /**
     * The classes under the root that use the trait, for checks that target
     * their descendants. Read once at registration.
     *
     * @param non-empty-string $trait
     */
    public function traitComposers(string $trait): TraitComposers
    {
        return $this->root()->traitComposers($trait);
    }

    /**
     * Symbols marked `@deprecated` under the root, for the deprecation checks
     * Mago has none of. Read once at registration.
     */
    public function deprecatedSymbols(): DeprecatedSymbols
    {
        return $this->root()->deprecatedSymbols();
    }

    /**
     * The descendant list costs one codebase request and goes into the key of
     * the shared entry, next to the run identity.
     */
    private function buildEntityTypes(Codebase $codebase): EntityTypeIndex
    {
        $names = $codebase->getClassDescendants(EntityTypeIndex::ENTITY_INTERFACE);
        $run = $this->run($names, $codebase);
        $cache = $this->root()->cache();
        $built =
            $cache === null || $run === null
                ? EntityTypeIndex::fromDescendants($codebase, $names)
                : $cache->shared(
                    'entity-types',
                    $run,
                    static fn(): EntityTypeIndex => EntityTypeIndex::fromDescendants($codebase, $names),
                    [EntityTypeIndex::class, EntityTypeDefinition::class],
                );

        return $built->merge($this->annotated()->entityTypes);
    }

    private function buildPlugins(Codebase $codebase): PluginIndex
    {
        $names = [];
        foreach ($codebase->getMultipleClassDescendants([
            PluginIndex::PLUGIN_ROOT,
            ...PluginIndex::OTHER_ROOTS,
        ]) as $descendants) {
            $names = [...$names, ...$descendants];
        }

        $names = array_values(array_unique($names));
        $run = $this->run($names, $codebase);
        $cache = $this->root()->cache();
        $built =
            $cache === null || $run === null
                ? PluginIndex::fromDescendants($codebase, $names)
                : $cache->shared(
                    'plugins',
                    $run,
                    static fn(): PluginIndex => PluginIndex::fromDescendants($codebase, $names),
                    [PluginIndex::class],
                );

        return $built->merge($this->annotated()->plugins);
    }

    /**
     * Identifies the run for the shared cache: the host process every worker
     * is a child of, Mago's generation for the frozen codebase, and a hash of
     * the class names the index is built from.
     *
     * The generation is what makes an entry unusable once the same host
     * analyzes again, as a watch or editor session does. The names change
     * only when a class is added or removed, so an edited entity id, storage
     * handler or plugin attribute leaves them alone.
     *
     * Null means the generation or a host identity that tells runs apart could
     * not be read, and then there is nothing to key an entry on that a second
     * analysis would not match. The caller builds its own index instead of
     * sharing one.
     *
     * @param list<string> $names
     */
    private function run(array $names, Codebase $codebase): ?string
    {
        $generation = AnalysisGeneration::of($codebase);
        $host = HostProcess::shareable();
        if ($generation === null || $host === null) {
            return null;
        }

        return $host . '-g' . (string) $generation . '-' . sha1(implode("\n", $names));
    }

    /**
     * Starts over when the codebase belongs to a new analysis.
     *
     * A watch or editor session analyzes again in the same worker, and an
     * incremental analysis runs the codebase scan only when a file it targets
     * changed. An edited services or schema file, or an edited entity class,
     * reaches no scan hook, so the scan cannot be what drops the indexes. The
     * generation on the codebase moves with every analysis. The scan results
     * are kept: a scan that did not run found nothing new.
     */
    private function follow(Codebase $codebase): void
    {
        $generation = AnalysisGeneration::of($codebase);
        if ($generation === null || $generation === $this->generation) {
            return;
        }

        if ($this->generation !== null) {
            $this->forget();
        }

        $this->generation = $generation;
    }

    /**
     * Drops every index built from files or from the codebase.
     */
    private function forget(): void
    {
        $this->root = null;
        $this->yaml = null;
        $this->services = null;
        $this->entityTypes = null;
        $this->plugins = null;
        $this->configSchema = null;
        $this->hookFunctions = null;
        $this->profileThemes = null;
    }

    private function root(): DrupalRoot
    {
        if ($this->root === null) {
            $cwd = getcwd();
            $this->root = DrupalRoot::discover($cwd === false ? '.' : $cwd, $this->rootPath, $this->cache);
        }

        return $this->root;
    }

    /**
     * Runs a metadata-backed build once even when several requests arrive at
     * the same time. The worker runs each request in its own fiber and a
     * codebase query suspends the fiber, so a plain null check lets every
     * concurrent request start its own build; later fibers wait here instead.
     * A build that throws hands the turn to exactly one waiter. Returns what
     * this request's own build returned, or null when another request built.
     *
     * @template T
     *
     * @param Closure(): bool $built
     * @param Closure(): T $build
     * @return T|null
     */
    private function once(string $name, Closure $built, Closure $build): mixed
    {
        while (!$built()) {
            if (array_key_exists($name, $this->waiting)) {
                /** @var Suspension<null> $suspension */
                $suspension = EventLoop::getSuspension();
                $this->waiting[$name][] = $suspension;
                $suspension->suspend();
                continue;
            }

            $this->waiting[$name] = [];
            try {
                return $build();
            } finally {
                /** @var list<Suspension<null>> $waiting */
                $waiting = $this->waiting[$name];
                unset($this->waiting[$name]);
                foreach ($waiting as $suspension) {
                    $suspension->resume();
                }
            }
        }

        return null;
    }
}
