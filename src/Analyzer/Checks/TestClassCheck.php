<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use function preg_match;
use function str_contains;
use function str_ends_with;

/**
 * Codes and the text gate of the test class conventions; TestClassHook
 * does the checking.
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

    /**
     * The modifiers and the name of a class declaration.
     */
    private const DECLARATION = '/((?:(?:abstract|final|readonly)\s+)*)class\s+([A-Za-z_]\w*)\s*(?:extends\b|implements\b|\{)/';

    /**
     * A public `$modules` declaration, static or not, typed or not.
     */
    private const PUBLIC_MODULES = '/\bpublic\s+(?:static\s+)?(?:\??array\s+)?\$modules\b/';

    private function __construct() {}

    /**
     * Whether the class text leaves something to report: a concrete class
     * whose name does not end in `Test`, or a public `$modules`. Most test
     * classes are neither, and cost no codebase request then. A declaration
     * the pattern does not read goes on to the lookups.
     */
    public static function mayReport(string $text): bool
    {
        $matches = [];
        if (preg_match(self::DECLARATION, $text, $matches) !== 1) {
            return true;
        }

        $concrete = !str_contains($matches[1], 'abstract');

        return $concrete && !str_ends_with($matches[2], 'Test') || preg_match(self::PUBLIC_MODULES, $text) === 1;
    }
}
