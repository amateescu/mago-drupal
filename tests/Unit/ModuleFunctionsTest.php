<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ModuleFunctions;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function dirname;
use function sort;

final class ModuleFunctionsTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/module_functions/foo';

    public function testReadsTheFunctionsOfProceduralFilesOutsideSrcAndVendor(): void
    {
        $names = array_keys(ModuleFunctions::names(self::FIXTURE));
        sort($names);

        self::assertSame(['foo_admin_validate', 'foo_form_submit', 'foo_sub_submit'], $names);
    }

    public function testTheInnermostModuleOwnsAFile(): void
    {
        $modules = self::modules();
        $file = self::FIXTURE . '/modules/foo_sub/foo_sub.module';

        self::assertSame(['foo_sub', $modules['foo_sub']], ModuleFunctions::owner($modules, $file, 'foo_sub_submit'));
        // A function named after the outer module, written in the submodule,
        // is not the submodule's.
        self::assertNull(ModuleFunctions::owner($modules, $file, 'foo_form_submit'));
    }

    public function testTheFunctionHasToBeNamedAfterTheModule(): void
    {
        $modules = self::modules();
        $file = self::FIXTURE . '/foo.module';

        self::assertSame(['foo', $modules['foo']], ModuleFunctions::owner($modules, $file, 'foo_submit'));
        self::assertSame(['foo', $modules['foo']], ModuleFunctions::owner($modules, $file, '_foo_submit'));
        self::assertSame(['foo', $modules['foo']], ModuleFunctions::owner($modules, $file, 'Foo_Submit'));
        self::assertNull(ModuleFunctions::owner($modules, $file, 'foobar_submit'));
        self::assertNull(ModuleFunctions::owner($modules, $file, 'other_submit'));
    }

    /**
     * A function named after a module whose name is longer and starts with
     * the file's module is that module's, wherever it lives.
     */
    public function testAModuleWithALongerNameClaimsTheFunction(): void
    {
        $modules = self::modules();
        $modules['foo_bar'] = dirname($modules['foo']) . '/foo_bar';
        $file = self::FIXTURE . '/foo.module';

        self::assertNull(ModuleFunctions::owner($modules, $file, 'foo_bar_submit'));
        self::assertNull(ModuleFunctions::owner($modules, $file, '_foo_bar_submit'));
        self::assertSame(['foo', $modules['foo']], ModuleFunctions::owner($modules, $file, 'foo_baz_submit'));
        self::assertSame(['foo', $modules['foo']], ModuleFunctions::owner($modules, $file, 'foo_barn_submit'));
    }

    public function testAFileOutsideEveryModuleHasNoOwner(): void
    {
        self::assertNull(ModuleFunctions::owner(self::modules(), __FILE__, 'foo_submit'));
        self::assertNull(ModuleFunctions::owner(self::modules(), self::FIXTURE . '/missing.module', 'foo_submit'));
    }

    /**
     * The module index lists real directories.
     *
     * @return array<string, string>
     */
    private static function modules(): array
    {
        $foo = (string) realpath(self::FIXTURE);

        return ['foo' => $foo, 'foo_sub' => $foo . '/modules/foo_sub', 'other' => dirname($foo) . '/other'];
    }
}
