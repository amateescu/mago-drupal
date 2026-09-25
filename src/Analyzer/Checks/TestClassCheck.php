<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

/**
 * Codes of the test class conventions; TestClassHook does the checking.
 *
 * Ports phpstan-drupal's TestClassSuffixNameRule and
 * TestClassesProtectedPropertyModulesRule.
 *
 * @internal
 */
final class TestClassCheck
{
    public const SUFFIX_CODE = 'test-class-suffix';

    public const MODULES_CODE = 'test-modules-visibility';

    public const ANCESTORS = ['PHPUnit\Framework\TestCase'];

    private function __construct() {}
}
