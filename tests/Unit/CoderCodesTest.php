<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use amateescu\MagoDrupal\Internal\CoderCodes;
use PHPUnit\Framework\TestCase;

use function array_filter;
use function array_intersect;
use function array_merge;
use function array_values;
use function dirname;
use function explode;
use function in_array;
use function ksort;
use function sort;
use function str_starts_with;

/**
 * Keeps the Coder codes that phpcs comments are matched against in step with
 * the parity map.
 */
final class CoderCodesTest extends TestCase
{
    public function testPortsMatchTheMap(): void
    {
        $map = CoderMap::load(dirname(__DIR__, levels: 2) . '/tests/coder-map.json');
        $expected = [];
        foreach ($map->sniffs as $sniff) {
            foreach ($sniff['codes'] as $code) {
                foreach (array_filter(CoderMap::where($sniff, $code), self::isPort(...)) as $handler) {
                    $expected[explode(separator: ':', string: $handler, limit: 2)[1]][] =
                        $sniff['sniff'] . '.' . $code['code'];
                }
            }
        }

        foreach ($expected as $rule => $codes) {
            sort($codes);
            $expected[$rule] = $codes;
        }

        ksort($expected);

        self::assertSame($expected, CoderCodes::PORTS);
    }

    /**
     * The other names belong to registered rules, and none of them is a
     * name from the map.
     */
    public function testOtherNamesBelongToRules(): void
    {
        $rules = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $rules[] = $rule->getDefinition()->code;
        }

        $ported = array_merge(...array_values(CoderCodes::PORTS));
        foreach (CoderCodes::OTHER_NAMES as $rule => $codes) {
            self::assertTrue(in_array($rule, $rules, strict: true), $rule);
            self::assertSame([], array_values(array_intersect($codes, $ported)), $rule);
        }

        self::assertSame(
            [
                'VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable',
                'DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable',
            ],
            CoderCodes::of('drupal/unused-variable'),
        );
        self::assertSame([], CoderCodes::of('drupal/render-callback'));
    }

    /**
     * Whether a handler of the map is a rule or a check of this extension.
     */
    private static function isPort(string $handler): bool
    {
        return str_starts_with($handler, 'ext:') || str_starts_with($handler, 'check:');
    }
}
