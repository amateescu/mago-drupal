<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_values;
use function dirname;

/**
 * Mago only warns about a code in `[linter.rules]` that doesn't name a rule,
 * and doesn't check the codes in `[analyzer] ignore` at all, so a typo in
 * the config files would turn nothing off.
 */
final class PresetsTest extends TestCase
{
    public function testTheConfigFilesNameRegisteredRules(): void
    {
        $registered = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $registered[] = $rule->getDefinition()->code;
        }

        self::assertSame([], array_values(array_diff(CorePreset::ruleCodes(), $registered)));
        self::assertSame([], array_values(array_diff(CorePreset::ruleCodes('drupal.mago.toml'), $registered)));
    }

    public function testTheCoreConfigFileIgnoresChecksThatExist(): void
    {
        $root = dirname(__DIR__, levels: 2);
        $pages = new CheckPages(new DocsPages($root), $root);
        $checks = $pages->pinnedCodes();

        self::assertNotSame([], CorePreset::ignoredChecks());
        self::assertSame([], array_values(array_diff(CorePreset::ignoredChecks(), $checks)));
    }
}
