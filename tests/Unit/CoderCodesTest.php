<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use amateescu\MagoDrupal\Internal\CoderCodes;
use amateescu\MagoDrupal\Internal\CoderPorts;
use PHPUnit\Framework\TestCase;

use function array_intersect;
use function array_merge;
use function array_values;
use function in_array;

/**
 * Checks the other names that phpcs comments are matched against.
 */
final class CoderCodesTest extends TestCase
{
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

        $ported = array_merge(...array_values(CoderPorts::PORTS));
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
}
