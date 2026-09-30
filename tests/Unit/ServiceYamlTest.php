<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ServiceYaml;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function uniqid;

final class ServiceYamlTest extends TestCase
{
    /**
     * A test module's services file adds its own services, and only replaces
     * one that no file outside the tests defines.
     */
    public function testTestModulesDoNotReplaceServices(): void
    {
        $temporary = sys_get_temp_dir() . '/mago-drupal-test-' . uniqid();
        $core = $temporary . '/core/core.services.yml';
        $test = $temporary . '/core/modules/update/tests/modules/update_test/update_test.services.yml';
        $module = $temporary . '/modules/clock/clock.services.yml';
        mkdir($temporary . '/core/modules/update/tests/modules/update_test', recursive: true);
        mkdir($temporary . '/modules/clock', recursive: true);
        file_put_contents($core, data: "services:\n  datetime.time:\n    class: Drupal\\Component\\Datetime\\Time\n");
        file_put_contents(
            $test,
            data: "services:\n  datetime.time:\n    class: Drupal\\update_test\\TestTime\n"
            . "  update_test.only:\n    class: Drupal\\update_test\\Only\n  clock.time:\n    class: Drupal\\update_test\\TestTime\n",
        );
        file_put_contents($module, data: "services:\n  clock.time:\n    class: Drupal\\clock\\Time\n");

        [$definitions] = ServiceYaml::read([$core, $test, $module]);

        self::assertSame('Drupal\Component\Datetime\Time', $definitions['datetime.time']['class'] ?? null);
        self::assertSame('Drupal\update_test\Only', $definitions['update_test.only']['class'] ?? null);
        // A file from outside the tests replaces what a test module defined.
        self::assertSame('Drupal\clock\Time', $definitions['clock.time']['class'] ?? null);

        DiskCacheTest::remove($temporary);
    }
}
