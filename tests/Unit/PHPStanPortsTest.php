<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use amateescu\MagoDrupal\Internal\PHPStanDrupalPorts;
use amateescu\MagoDrupal\Internal\PHPStanPorts;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function in_array;

/**
 * Checks core's PHPStan identifiers that `@phpstan-ignore` comments are
 * matched against.
 */
final class PHPStanPortsTest extends TestCase
{
    /**
     * The rules for core's own PHPStan settings are registered rules that the
     * map doesn't list.
     */
    public function testCoreRulesAreRegistered(): void
    {
        $rules = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $rules[] = $rule->getDefinition()->code;
        }

        foreach (PHPStanPorts::CORE as $code => $identifiers) {
            self::assertTrue(in_array($code, $rules, strict: true), $code);
            self::assertFalse(array_key_exists($code, PHPStanDrupalPorts::PORTS), $code);
            self::assertSame($identifiers, PHPStanPorts::of($code));
        }

        self::assertNull(PHPStanPorts::of('drupal/property-name'));
    }
}
