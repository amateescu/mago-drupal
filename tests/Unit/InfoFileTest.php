<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\InfoFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class InfoFileTest extends TestCase
{
    private static string $root = '';

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir() . '/' . uniqid(prefix: 'info-file-');
        foreach ([
            'mymodule/src/Form',
            'mymodule/includes',
            'legacy',
            'both',
            'other',
            'six',
            'versionless',
        ] as $directory) {
            mkdir(self::$root . '/' . $directory, recursive: true);
        }

        foreach ([
            'mymodule/mymodule.info.yml',
            'mymodule/mymodule_extra.info.yml',
            'legacy/legacy.info',
            'both/both_yml.info.yml',
            'both/b.info',
        ] as $file) {
            file_put_contents(self::$root . '/' . $file, data: "name: Test\n");
        }

        file_put_contents(self::$root . '/legacy/legacy.info', data: "name = Legacy\ncore = 7.x\n");
        file_put_contents(self::$root . '/six/six.info', data: "name = Six\ncore = \"6.x\"\n");
        file_put_contents(self::$root . '/versionless/versionless.info', data: "name = Versionless\n");
    }

    public static function tearDownAfterClass(): void
    {
        self::remove(self::$root);
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function paths(): iterable
    {
        yield 'module file' => ['mymodule/mymodule.module', 'mymodule'];
        yield 'module file away from its info file' => ['other/named.install', 'named'];
        yield 'class two directories below the info file' => ['mymodule/src/Form/Foo.php', 'mymodule'];
        yield 'include file' => ['mymodule/includes/mymodule.pages.inc', 'mymodule'];
        yield 'shortest info file name' => ['mymodule/x.inc', 'mymodule'];
        yield 'Drupal 7 info file' => ['legacy/legacy.pages.inc', 'legacy'];
        yield 'info.yml before info' => ['both/x.inc', 'both_yml'];
        yield 'no info file' => ['other/x.php', null];
    }

    #[DataProvider('paths')]
    public function testFindsTheNameAsCoderDoes(string $path, ?string $name): void
    {
        self::assertSame($name, InfoFile::moduleName(self::$root . '/' . $path));
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function versions(): iterable
    {
        yield 'info.yml file' => ['mymodule/src/Form/Foo.php', 8];
        yield 'no info file' => ['other/x.php', 8];
        yield 'Drupal 7 module file' => ['legacy/legacy.module', 7];
        yield 'quoted Drupal 6 version' => ['six/six.module', 6];
        yield 'info file without a core line' => ['versionless/x.inc', 7];
        yield 'info.yml next to an info file' => ['both/x.inc', 8];
    }

    #[DataProvider('versions')]
    public function testReadsTheCoreVersionAsCoderDoes(string $path, int $version): void
    {
        self::assertSame($version, InfoFile::coreVersion(self::$root . '/' . $path));
    }

    /**
     * Deletes a directory with its contents.
     */
    private static function remove(string $path): void
    {
        if (!is_dir($path)) {
            unlink($path);

            return;
        }

        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            self::remove($path . '/' . (string) $entry);
        }

        rmdir($path);
    }
}
