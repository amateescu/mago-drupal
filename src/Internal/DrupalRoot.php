<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Closure;
use Throwable;

use function file_get_contents;
use function glob;
use function is_file;
use function json_decode;
use function preg_match;
use function realpath;
use function rtrim;
use function str_ends_with;
use function str_starts_with;

use const DIRECTORY_SEPARATOR;
use const GLOB_ONLYDIR;

/**
 * Locates the Drupal document root.
 *
 * Mago starts workers in the directory of the effective `mago.toml`, so the
 * search starts from the worker's cwd. The root is found in this order: an
 * explicit path, the Composer scaffold's `web-root`, a well-known subdirectory
 * holding `core/lib/Drupal.php`, and finally the cwd itself. The last fallback
 * keeps a workspace without core usable, such as this package's own corpus.
 *
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class DrupalRoot
{
    /**
     * Profile and theme directories, which the module map leaves out; one
     * level of `contrib`/`custom` nesting is covered.
     */
    private const EXTENSION_GLOBS = [
        'core/profiles/*',
        'core/themes/*',
        'profiles/*',
        'profiles/*/*',
        'themes/*',
        'themes/*/*',
    ];

    /**
     * @var list<string>|null
     */
    private ?array $sourceFiles = null;

    private const WEB_ROOT_CANDIDATES = ['', 'web', 'docroot', 'html', 'public'];

    private ?ExtensionFileSet $files = null;

    private readonly ?DiskCache $cache;

    private function __construct(
        public readonly string $path,
        ?DiskCache $cache,
    ) {
        $real = realpath($path);
        $this->cache = $cache?->scoped($real === false ? $path : $real);
    }

    /**
     * Uses a known root without probing.
     */
    public static function at(string $path, ?DiskCache $cache = null): self
    {
        return new self(rtrim($path, DIRECTORY_SEPARATOR), $cache);
    }

    /**
     * Finds the root for a worker started in `$cwd`.
     */
    public static function discover(string $cwd, ?string $override = null, ?DiskCache $cache = null): self
    {
        $cwd = rtrim($cwd, DIRECTORY_SEPARATOR);
        if ($override !== null) {
            return self::at(
                str_starts_with($override, DIRECTORY_SEPARATOR) ? $override : $cwd . '/' . $override,
                $cache,
            );
        }

        $candidates = self::WEB_ROOT_CANDIDATES;
        $scaffolded = self::scaffoldWebRoot($cwd . '/composer.json');
        if ($scaffolded !== null) {
            $candidates = [$scaffolded, ...$candidates];
        }

        foreach ($candidates as $candidate) {
            $directory = $candidate === '' ? $cwd : $cwd . '/' . rtrim($candidate, DIRECTORY_SEPARATOR);
            if (is_file($directory . '/core/lib/Drupal.php')) {
                return new self($directory, $cache);
            }
        }

        return new self($cwd, $cache);
    }

    /**
     * The cache scoped to this root, or null when caching is off.
     */
    public function cache(): ?DiskCache
    {
        return $this->cache;
    }

    /**
     * Every `*.services.yml` owned by an extension under this root.
     *
     * @return list<string>
     */
    public function serviceFiles(): array
    {
        return $this->files()->services;
    }

    /**
     * Every `config/schema/*.schema.yml` owned by an extension under this root.
     *
     * @return list<string>
     */
    public function schemaFiles(): array
    {
        return $this->files()->schemas;
    }

    /**
     * Module machine name to directory, from every module's `*.info.yml`.
     *
     * @return array<string, string>
     */
    public function modules(): array
    {
        return $this->files()->modules;
    }

    /**
     * Every `*.api.php` under this root, core first.
     *
     * @return list<string>
     */
    public function apiFiles(): array
    {
        return $this->files()->apiFiles;
    }

    /**
     * Classes marked `@internal` in the extension source under this root,
     * parsed through the cache keyed by the files' modification times and
     * sizes.
     */
    public function internalClasses(): InternalClasses
    {
        $files = $this->sourceFiles();

        return $this->cached(
            'internal',
            $files,
            static fn(): InternalClasses => InternalClasses::fromFiles($files),
            [InternalClasses::class],
        );
    }

    /**
     * Service ids that `*ServiceProvider.php` classes under this root register
     * in PHP. Only the ids are read; the scan hook types the providers in the
     * analyzed paths, and this covers the ones in includes, core's included.
     *
     * @return array<non-empty-string, Definition>
     */
    public function providerIds(): array
    {
        $files = [];
        foreach ($this->sourceFiles() as $file) {
            if (!str_ends_with($file, 'ServiceProvider.php')) {
                continue;
            }

            $files[] = $file;
        }

        return $this->cached('provider-ids', $files, static fn(): array => ServiceDefinitions::idsInFiles($files));
    }

    /**
     * Every PHP file of `core/lib`, `core/tests` and the `src` directory of
     * every module, profile and theme under the root.
     *
     * Every worker walks this at registration, before it answers anything, so
     * a cached listing is reused while every directory it read keeps its
     * modification time. On a core checkout the walk is 9,600 files.
     *
     * @return list<string>
     */
    private function sourceFiles(): array
    {
        if ($this->sourceFiles !== null) {
            return $this->sourceFiles;
        }

        $directories = $this->sourceDirectories();
        /** @var mixed $cached */
        $cached = $this->cache?->get('source-files', 'root', [SourceFileSet::class]);
        try {
            $current = $cached instanceof SourceFileSet && $cached->isCurrent($directories);
        } catch (Throwable) {
            // A payload from another shape of the class misses.
            $current = false;
        }

        if ($current && $cached instanceof SourceFileSet) {
            return $this->sourceFiles = $cached->files;
        }

        $walked = SourceFileSet::of($directories);
        $this->cache?->set('source-files', 'root', $walked);

        return $this->sourceFiles = $walked->files;
    }

    /**
     * The directories the source walk starts from, absent ones included.
     *
     * @return list<string>
     */
    private function sourceDirectories(): array
    {
        $directories = [$this->path . '/core/lib', $this->path . '/core/tests'];
        foreach ($this->modules() as $directory) {
            $directories[] = $directory . '/src';
        }

        foreach (self::EXTENSION_GLOBS as $pattern) {
            $found = glob($this->path . '/' . $pattern . '/src', GLOB_ONLYDIR);
            $directories = [...$directories, ...($found === false ? [] : $found)];
        }

        return $directories;
    }

    /**
     * The `Drupal::VERSION` constant, read off `core/lib/Drupal.php`; null
     * without a core checkout.
     */
    public function coreVersion(): ?string
    {
        $source = is_file($this->path . '/core/lib/Drupal.php')
            ? file_get_contents($this->path . '/core/lib/Drupal.php')
            : false;
        $matches = [];
        if ($source === false || preg_match("/const VERSION = '([^']+)'/", $source, $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /**
     * One walk serves every list. A cached walk is reused while every
     * directory it read or probed keeps its modification time; editing a file
     * changes no directory, and the walk only lists files, so that is right.
     */
    private function files(): ExtensionFileSet
    {
        if ($this->files !== null) {
            return $this->files;
        }

        /** @var mixed $cached */
        $cached = $this->cache?->get('walk', 'root', [ExtensionFileSet::class]);
        try {
            $current = $cached instanceof ExtensionFileSet && $cached->isCurrent();
        } catch (Throwable) {
            // A payload from another shape of the class misses.
            $current = false;
        }

        if ($current && $cached instanceof ExtensionFileSet) {
            return $this->files = $cached;
        }

        $this->files = ExtensionFiles::collect($this);
        $this->cache?->set('walk', 'root', $this->files);

        return $this->files;
    }

    /**
     * Loads a parsed form of the files through the cache, keyed by their
     * current modification times and sizes.
     *
     * @template T
     * @param list<string> $paths
     * @param Closure(): T $parse
     * @param list<class-string> $classes Classes the parsed value may contain.
     * @return T
     */
    public function cached(string $kind, array $paths, Closure $parse, array $classes = []): mixed
    {
        if ($this->cache === null) {
            return $parse();
        }

        $fingerprint = DiskCache::fingerprint($paths);
        if ($fingerprint === null) {
            // A file is still being written. An entry keyed on it now would
            // never be read again.
            return $parse();
        }

        /** @var T|null $cached */
        $cached = $this->cache->get($kind, $fingerprint, $classes);
        if ($cached !== null) {
            return $cached;
        }

        $parsed = $parse();
        $this->cache->set($kind, $fingerprint, $parsed);

        return $parsed;
    }

    /**
     * Reads `extra.drupal-scaffold.locations.web-root` from a composer.json.
     */
    private static function scaffoldWebRoot(string $composerJson): ?string
    {
        if (!is_file($composerJson)) {
            return null;
        }

        $contents = file_get_contents($composerJson);
        if ($contents === false) {
            return null;
        }

        return Shape::stringAt(
            json_decode($contents, associative: true),
            'extra',
            'drupal-scaffold',
            'locations',
            'web-root',
        );
    }
}
