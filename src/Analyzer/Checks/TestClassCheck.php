<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use function in_array;
use function preg_match;
use function str_contains;
use function str_ends_with;
use function str_starts_with;
use function strtolower;

/**
 * Codes and the text gates of the test class conventions; TestClassHook
 * does the checking.
 *
 * Ports phpstan-drupal's TestClassSuffixNameRule and
 * TestClassesProtectedPropertyModulesRule, and core's own
 * ComponentTestDoesNotExtendCoreTest rule.
 *
 * @internal
 */
final class TestClassCheck
{
    public const SUFFIX_CODE = 'test-class-suffix';

    public const MODULES_CODE = 'test-modules-visibility';

    public const COMPONENT_CODE = 'component-test-core-base';

    public const ANCESTORS = ['PHPUnit\Framework\TestCase'];

    /**
     * Component tests run without Drupal, so they cannot extend the test
     * bases that boot it or need its classes. WebDriverTestBase extends
     * BrowserTestBase.
     */
    private const CORE_BASES = [
        'Drupal\Tests\UnitTestCase',
        'Drupal\BuildTests\Framework\BuildTestBase',
        'Drupal\KernelTests\KernelTestBase',
        'Drupal\Tests\BrowserTestBase',
    ];

    /**
     * The namespace of core's component tests, lowercased.
     */
    private const COMPONENT_TESTS = 'drupal\tests\component\\';

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

    /**
     * Whether the file may declare a component test. The class name decides
     * once the class is looked up.
     */
    public static function mayBeComponentTest(string $contents): bool
    {
        return str_contains($contents, 'namespace Drupal\Tests\Component');
    }

    /**
     * The core test base a component test extends, or null. Only the class
     * whose own parent lies outside the component tests is reported, so a
     * component test base that extends a core one is reported once, and not
     * again on every test below it.
     *
     * @param list<string> $ancestors Every parent class of the class.
     */
    public static function coreBase(string $class, ?string $parent, array $ancestors): ?string
    {
        if (
            !str_starts_with(strtolower($class), self::COMPONENT_TESTS)
            || $parent === null
            || str_starts_with(strtolower($parent), self::COMPONENT_TESTS)
        ) {
            return null;
        }

        $lowercased = [];
        foreach ($ancestors as $ancestor) {
            $lowercased[] = strtolower($ancestor);
        }

        foreach (self::CORE_BASES as $base) {
            if (in_array(strtolower($base), $lowercased, strict: true)) {
                return $base;
            }
        }

        return null;
    }
}
