<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use amateescu\MagoDrupal\Linter\Rules\WeakHashRule;
use Mago\Sdk\Reporting\Level;
use PHPUnit\Framework\TestCase;

final class DrupalExtensionTest extends TestCase
{
    public function testFactoryOwnsStableRegistration(): void
    {
        $extension = DrupalExtension::create();

        self::assertSame('amateescu/mago-drupal', $extension->identifier);
        self::assertSame('Drupal', $extension->name);
        self::assertCount(1, $extension->linterRules);
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

    public function testWeakHashRuleDefinition(): void
    {
        $definition = (new WeakHashRule())->getDefinition();

        self::assertSame('drupal/weak-hash', $definition->code);
        self::assertSame(Level::Warning, $definition->defaultLevel);
        self::assertTrue($definition->defaultEnabled);
        self::assertNotEmpty($definition->targets);
    }
}
