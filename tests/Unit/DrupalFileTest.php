<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DrupalFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DrupalFileTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function paths(): iterable
    {
        yield 'module' => ['modules/node/node.module', 'module', 'node'];
        yield 'install' => ['modules/node/node.install', 'install', 'node'];
        yield 'multi-part include' => ['modules/node/node.pages.inc', 'inc', 'node'];
        yield 'class file' => ['modules/node/src/NodeStorage.php', 'php', 'NodeStorage'];
        yield 'no directory' => ['node.module', 'module', 'node'];
        yield 'no extension' => ['Makefile', '', 'Makefile'];
        yield 'uppercase extension' => ['node/node.MODULE', 'module', 'node'];
    }

    #[DataProvider('paths')]
    public function testReadsExtensionAndName(string $path, string $extension, string $name): void
    {
        $file = DrupalFile::fromPath($path);

        self::assertSame($extension, $file->extension);
        self::assertSame($name, $file->name);
    }

    public function testRecognisesProceduralFiles(): void
    {
        foreach (DrupalFile::PROCEDURAL_EXTENSIONS as $extension) {
            self::assertTrue(DrupalFile::fromPath("node/node.{$extension}")->isProcedural());
        }

        self::assertFalse(DrupalFile::fromPath('node/src/Node.php')->isProcedural());
        self::assertFalse(DrupalFile::fromPath('Makefile')->isProcedural());
    }

    /**
     * The corpus config lists the scanned extensions on its own, so this pins
     * that copy to the constant instead of trusting it.
     */
    public function testCorpusConfigScansEveryProceduralExtension(): void
    {
        $config = (string) file_get_contents(__DIR__ . '/../corpus/mago.toml');

        $matches = [];
        self::assertSame(1, preg_match('/^extensions\s*=\s*\[(?<list>[^\]]*)\]/m', $config, $matches));

        $found = [];
        preg_match_all('/"([^"]+)"/', $matches['list'], $found);

        foreach (DrupalFile::PROCEDURAL_EXTENSIONS as $extension) {
            self::assertContains($extension, $found[1], "tests/corpus/mago.toml does not scan .{$extension} files.");
        }
    }

    public function testMatchesHookImplementations(): void
    {
        $file = DrupalFile::fromPath('modules/node/node.module');

        self::assertTrue($file->implementsHook('node_install', 'install'));
        self::assertFalse($file->implementsHook('node_install', 'uninstall'));
        // A different module implementing the hook is not this file's hook.
        self::assertFalse($file->implementsHook('user_install', 'install'));
    }
}
