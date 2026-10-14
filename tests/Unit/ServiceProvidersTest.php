<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ServiceProviders;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ServiceProvidersTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function paths(): iterable
    {
        yield 'core' => ['web/core/lib/Drupal/Core/CoreServiceProvider.php', true];
        yield 'module' => ['web/core/modules/content_moderation/src/ContentModerationServiceProvider.php', true];
        yield 'contrib module' => ['modules/contrib/search_api/src/SearchApiServiceProvider.php', true];
        yield 'workspace is the module' => ['src/TrashServiceProvider.php', true];
        yield 'installer' => ['core/lib/Drupal/Core/Installer/InstallerServiceProvider.php', false];
        yield 'named after another module' => ['modules/foo/src/BarServiceProvider.php', false];
        yield 'not in src' => ['modules/foo/FooServiceProvider.php', false];
        yield 'test module' => ['core/modules/system/tests/modules/foo/src/FooServiceProvider.php', false];
    }

    #[DataProvider('paths')]
    public function testFindsTheProvidersDrupalRegisters(string $path, bool $expected): void
    {
        self::assertSame($expected, ServiceProviders::discovered($path));
    }
}
