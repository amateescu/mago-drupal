<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DiskCache;
use amateescu\MagoDrupal\Internal\DrupalRoot;
use amateescu\MagoDrupal\Internal\ExtensionFileSet;
use amateescu\MagoDrupal\Internal\SourceFileSet;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function dirname;
use function file_get_contents;
use function file_put_contents;
use function glob;
use function ksort;
use function mkdir;
use function serialize;
use function sort;
use function str_replace;
use function strlen;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unserialize;

/**
 * @mago-expect lint:too-many-methods
 */
final class DrupalRootTest extends TestCase
{
    private static function roots(): string
    {
        return dirname(__DIR__) . '/fixtures/roots';
    }

    public function testPrefersTheComposerScaffoldWebRoot(): void
    {
        self::assertSame(self::roots() . '/scaffold/docroot', DrupalRoot::discover(self::roots() . '/scaffold')->path);
    }

    public function testProbesTheUsualSubdirectories(): void
    {
        self::assertSame(self::roots() . '/web/web', DrupalRoot::discover(self::roots() . '/web/')->path);
    }

    public function testFallsBackToTheWorkingDirectory(): void
    {
        self::assertSame(self::roots() . '/bare', DrupalRoot::discover(self::roots() . '/bare')->path);
    }

    public function testHonoursAnExplicitRoot(): void
    {
        self::assertSame(
            self::roots() . '/scaffold/docroot',
            DrupalRoot::discover('/nowhere', self::roots() . '/scaffold/docroot/')->path,
        );
        self::assertSame(
            self::roots() . '/scaffold/docroot',
            DrupalRoot::discover(self::roots() . '/scaffold', 'docroot')->path,
        );
    }

    /**
     * `modules/custom/loop` links back to the root; the walk has to end and
     * list every file once.
     */
    public function testListsPairedServiceFilesCoreFirst(): void
    {
        $files = DrupalRoot::discover(self::roots() . '/scaffold')->serviceFiles();

        self::assertSame(
            [
                'core/core.services.yml',
                'core/modules/system/system.services.yml',
                'modules/custom/alpha/alpha.services.yml',
                'sites/default/modules/beta/beta.services.yml',
            ],
            array_map(static fn(string $file): string => str_replace(
                self::roots() . '/scaffold/docroot/',
                replace: '',
                subject: $file,
            ), $files),
        );
    }

    public function testListsOwnedSchemaFilesCoreFirst(): void
    {
        $files = DrupalRoot::discover(self::roots() . '/scaffold')->schemaFiles();

        self::assertSame(
            [
                'core/config/schema/core.data_types.schema.yml',
                'core/themes/stark/config/schema/stark.schema.yml',
                // Every `*.yml` in the directory is schema, as in Drupal.
                'modules/custom/alpha/config/schema/alpha.data_types.yml',
                'modules/custom/alpha/config/schema/alpha.schema.yml',
                'themes/custom/gamma/config/schema/gamma.schema.yml',
            ],
            array_map(static fn(string $file): string => str_replace(
                self::roots() . '/scaffold/docroot/',
                replace: '',
                subject: $file,
            ), $files),
        );
    }

    public function testWalksABareWorkspaceFromItsRoot(): void
    {
        self::assertSame(
            [self::roots() . '/bare/bare.services.yml'],
            DrupalRoot::at(self::roots() . '/bare')->serviceFiles(),
        );
    }

    public function testFindsNothingWithoutExtensions(): void
    {
        self::assertSame([], DrupalRoot::at(self::roots() . '/web/web')->serviceFiles());
    }

    /**
     * Themes are not modules, and a test copy of a module loses to the real
     * one whatever order the walk visits them in.
     */
    public function testMapsModulesToTheirDirectories(): void
    {
        $modules = DrupalRoot::discover(self::roots() . '/scaffold')->modules();
        ksort($modules);
        self::assertSame(
            [
                'alpha' => 'modules/custom/alpha',
                'beta' => 'sites/default/modules/beta',
                'system' => 'core/modules/system',
            ],
            array_map(static fn(string $path): string => str_replace(
                self::roots() . '/scaffold/docroot/',
                replace: '',
                subject: $path,
            ), $modules),
        );
    }

    public function testListsApiFilesCoreFirst(): void
    {
        self::assertSame(
            [
                'core/core.api.php',
                'core/lib/Drupal/Core/Entity/entity.api.php',
                'modules/custom/alpha/alpha.api.php',
            ],
            array_map(static fn(string $path): string => str_replace(
                self::roots() . '/scaffold/docroot/',
                replace: '',
                subject: $path,
            ), DrupalRoot::discover(self::roots() . '/scaffold')->apiFiles()),
        );
    }

    /**
     * The second discovery reads the cached walk, which a stale entry planted
     * in its place proves; adding a module under a site directory that did
     * not exist before invalidates it.
     */
    public function testReusesACachedWalkUntilADirectoryChanges(): void
    {
        $temporary = sys_get_temp_dir() . '/mago-drupal-test-' . uniqid();
        $root = $temporary . '/web';
        mkdir($root . '/core/lib', recursive: true);
        mkdir($root . '/modules/alpha', recursive: true);
        touch($root . '/core/lib/Drupal.php');
        touch($root . '/modules/alpha/alpha.info.yml');
        $cache = new DiskCache($temporary . '/cache');
        self::settle($root, '', '/core', '/core/lib', '/modules', '/modules/alpha');

        self::assertSame(['alpha'], array_keys(DrupalRoot::at($root, $cache)->modules()));
        $entries = glob($temporary . '/cache/*/walk-*.cache');
        self::assertNotFalse($entries);
        self::assertCount(1, $entries);

        $stored = file_get_contents($entries[0]);
        /** @var mixed $planted */
        $planted = unserialize($stored === false ? '' : $stored, ['allowed_classes' => [ExtensionFileSet::class]]);
        self::assertInstanceOf(ExtensionFileSet::class, $planted);
        file_put_contents(
            $entries[0],
            serialize(new ExtensionFileSet([], [], ['planted' => $root], [], $planted->directories)),
        );
        self::assertSame(['planted'], array_keys(DrupalRoot::at($root, $cache)->modules()));

        mkdir($root . '/sites/default/modules/beta', recursive: true);
        touch($root . '/sites/default/modules/beta/beta.info.yml');
        $modules = array_keys(DrupalRoot::at($root, $cache)->modules());
        sort($modules);
        self::assertSame(['alpha', 'beta'], $modules);

        // A payload from another shape of the class is a miss, not a crash.
        file_put_contents(
            $entries[0],
            'O:' . strlen(ExtensionFileSet::class) . ':"' . ExtensionFileSet::class . '":0:{}',
        );
        $modules = array_keys(DrupalRoot::at($root, $cache)->modules());
        sort($modules);
        self::assertSame(['alpha', 'beta'], $modules);

        DiskCacheTest::remove($temporary);
    }

    /**
     * Core's own schema lives outside any module, so the walk globs for it
     * and has to watch the directory it globbed.
     */
    public function testSeesACoreSchemaFileAddedAfterACachedWalk(): void
    {
        $temporary = sys_get_temp_dir() . '/mago-drupal-test-' . uniqid();
        $root = $temporary . '/web';
        mkdir($root . '/core/config/schema', recursive: true);
        mkdir($root . '/core/lib', recursive: true);
        touch($root . '/core/lib/Drupal.php');
        touch($root . '/core/config/schema/core.data_types.schema.yml');
        $cache = new DiskCache($temporary . '/cache');

        self::settle($root, '', '/core', '/core/config', '/core/config/schema', '/core/lib');

        self::assertCount(1, DrupalRoot::at($root, $cache)->schemaFiles());

        touch($root . '/core/config/schema/system.schema.yml');
        self::assertCount(2, DrupalRoot::at($root, $cache)->schemaFiles());

        DiskCacheTest::remove($temporary);
    }

    /**
     * Every worker walks the source directories at registration, so the
     * listing is cached and only walked again once a directory changes.
     */
    public function testReusesACachedSourceListingUntilADirectoryChanges(): void
    {
        $temporary = sys_get_temp_dir() . '/mago-drupal-test-' . uniqid();
        $root = $temporary . '/web';
        mkdir($root . '/modules/alpha/src', recursive: true);
        touch($root . '/modules/alpha/alpha.info.yml');
        file_put_contents($root . '/modules/alpha/src/Base.php', data: <<<'PHP'
            <?php
            namespace Drupal\alpha;
            /**
             * @internal
             */
            abstract class Base {}
            PHP);
        touch($root . '/modules/alpha/src', mtime: 1_000_000);
        $cache = new DiskCache($temporary . '/cache');

        self::assertSame(['Drupal\alpha\Base'], DrupalRoot::at($root, $cache)->internalClasses()->names());

        $entries = glob($temporary . '/cache/*/source-files-root-*.cache');
        self::assertNotFalse($entries);
        self::assertCount(1, $entries);

        $stored = file_get_contents($entries[0]);
        /** @var mixed $planted */
        $planted = unserialize($stored === false ? '' : $stored, ['allowed_classes' => [SourceFileSet::class]]);
        self::assertInstanceOf(SourceFileSet::class, $planted);
        file_put_contents($entries[0], serialize(new SourceFileSet($planted->candidates, [], $planted->directories)));
        self::assertSame([], DrupalRoot::at($root, $cache)->internalClasses()->names());

        // A file added to a walked directory changes its modification time.
        touch($root . '/modules/alpha/src/Other.php');
        self::assertSame(['Drupal\alpha\Base'], DrupalRoot::at($root, $cache)->internalClasses()->names());

        // A payload from another shape of the class is a miss, not a crash.
        file_put_contents($entries[0], 'O:' . strlen(SourceFileSet::class) . ':"' . SourceFileSet::class . '":0:{}');
        self::assertSame(['Drupal\alpha\Base'], DrupalRoot::at($root, $cache)->internalClasses()->names());

        DiskCacheTest::remove($temporary);
    }

    /**
     * Backdates the directories of a fixture tree. A directory written in the
     * last seconds is walked again whatever its modification time says, since
     * the time counts whole seconds and the listing may still be changing.
     */
    private static function settle(string $root, string ...$directories): void
    {
        foreach ($directories as $directory) {
            touch($root . $directory, mtime: 1_000_000);
        }
    }

    public function testReadsTheCoreVersion(): void
    {
        self::assertSame('11.4.6', DrupalRoot::discover(self::roots() . '/scaffold')->coreVersion());
        self::assertNull(DrupalRoot::at(self::roots() . '/bare')->coreVersion());
    }
}
