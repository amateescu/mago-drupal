<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use amateescu\MagoDrupal\Internal\PHPStanPorts;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_key_exists;
use function dirname;
use function in_array;
use function ksort;
use function sort;
use function str_starts_with;
use function substr;

/**
 * Keeps the PHPStan identifiers that `@phpstan-ignore` comments are matched
 * against, for the linter rules, in step with the phpstan-drupal map.
 */
final class PHPStanPortsTest extends TestCase
{
    public function testPortsMatchTheMap(): void
    {
        $map = PHPStanDrupalMap::load(dirname(__DIR__, levels: 2) . '/tests/phpstan-drupal-map.json');
        $expected = [];
        foreach ($map->rules as $rule) {
            foreach ($rule['identifiers'] as $identifier) {
                $handlers = PHPStanDrupalMap::where($rule, $identifier);
                foreach (array_filter($handlers, static fn(string $handler): bool => str_starts_with(
                    $handler,
                    'ext:',
                )) as $handler) {
                    $expected[substr($handler, offset: 4)][] = $identifier['identifier'];
                }
            }
        }

        foreach ($expected as $code => $identifiers) {
            sort($identifiers);
            $expected[$code] = $identifiers;
        }

        ksort($expected);

        self::assertSame($expected, PHPStanPorts::PORTS);
    }

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
            self::assertFalse(array_key_exists($code, PHPStanPorts::PORTS), $code);
            self::assertSame($identifiers, PHPStanPorts::of($code));
        }

        self::assertNull(PHPStanPorts::of('drupal/property-name'));
    }
}
