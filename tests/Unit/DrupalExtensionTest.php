<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\DrupalPlugin;
use amateescu\MagoDrupal\DrupalExtension;
use Mago\Sdk\Reporting\Level;
use PHPUnit\Framework\TestCase;

final class DrupalExtensionTest extends TestCase
{
    public function testFactoryOwnsStableRegistration(): void
    {
        $extension = DrupalExtension::create();

        self::assertSame('amateescu/mago-drupal', $extension->identifier);
        self::assertSame('Drupal', $extension->name);
        self::assertCount(1, $extension->analyzerPlugins);
        self::assertNull($extension->workerReducer);
    }

    /**
     * Rule codes end up in user baselines and @mago-expect comments, so they
     * are namespaced to Drupal rather than to the package vendor.
     */
    public function testRuleCodesAreNamespacedToDrupal(): void
    {
        foreach (DrupalExtension::create()->linterRules as $rule) {
            self::assertStringStartsWith('drupal/', $rule->getDefinition()->code);
        }
    }

    public function testRuleCodesAreUnique(): void
    {
        $codes = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $codes[] = $rule->getDefinition()->code;
        }

        self::assertSame($codes, array_unique($codes));
        self::assertNotEmpty($codes);
    }

    /**
     * Codes, default levels and enabled flags are public contract. Users
     * write them into baselines and @mago-expect pragmas. A rename or a level
     * flip has to fail here instead of shipping silently.
     */
    public function testRuleDefinitionsAreStable(): void
    {
        $expected = [
            'drupal/constant-prefix' => [Level::Warning, true],
            'drupal/deprecation-message' => [Level::Warning, true],
            'drupal/empty-install-hook' => [Level::Error, true],
            'drupal/enum-case-name' => [Level::Error, true],
            'drupal/global-function' => [Level::Warning, true],
            'drupal/global-variable' => [Level::Error, true],
            'drupal/install-hook-location' => [Level::Error, true],
            'drupal/link-text-translatable' => [Level::Error, true],
            'drupal/preg-security' => [Level::Error, true],
            'drupal/property-name' => [Level::Error, true],
            'drupal/redundant-use' => [Level::Error, true],
            'drupal/remote-address' => [Level::Error, true],
            'drupal/t-in-hook-menu' => [Level::Error, true],
            'drupal/t-in-hook-schema' => [Level::Error, true],
            'drupal/translatable-string' => [Level::Warning, true],
            'drupal/translated-exception' => [Level::Warning, true],
            'drupal/watchdog-message' => [Level::Error, true],
            'drupal/weak-hash' => [Level::Warning, true],
        ];

        $actual = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $definition = $rule->getDefinition();
            $actual[$definition->code] = [$definition->defaultLevel, $definition->defaultEnabled];
        }
        ksort($actual);

        self::assertSame($expected, $actual);
    }

    /**
     * Mago rejects a rule that subscribes to nothing, so a typo in a target
     * list should fail here rather than when a worker starts.
     */
    public function testEveryRuleSubscribesToNodeKinds(): void
    {
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $definition = $rule->getDefinition();

            self::assertNotEmpty($definition->targets, "{$definition->code} subscribes to no node kinds.");
            self::assertNotSame('', $definition->name);
            self::assertNotSame('', $definition->description);
        }
    }

    public function testCoreFlagReachesTheAnalyzerPlugin(): void
    {
        $default = DrupalExtension::create()->analyzerPlugins[0];
        $core = DrupalExtension::create(core: true)->analyzerPlugins[0];

        self::assertInstanceOf(DrupalPlugin::class, $default);
        self::assertInstanceOf(DrupalPlugin::class, $core);
        self::assertFalse($default->isCore());
        self::assertTrue($core->isCore());
    }
}
