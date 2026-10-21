<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\SourceText;
use Mago\Sdk\Analyzer\Metadata\PropertyMetadata;
use Mago\Sdk\Analyzer\Type\Visibility;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;

use function in_array;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strtolower;

/**
 * Codes and the text gates of the test class conventions; TestClassHook
 * does the checking.
 *
 * Ports phpstan-drupal's TestClassesProtectedPropertyModulesRule and
 * TestClassSuffixNameRule, and core's own ComponentTestDoesNotExtendCoreTest
 * rule. The linter rule `drupal/test-class-suffix` reports most misnamed
 * tests with no class lookup. The name check reports only the TestCase
 * descendants that the linter rule doesn't take for tests, see
 * TestClassSuffix.
 *
 * @internal
 */
final class TestClassCheck
{
    public const MODULES_CODE = 'test-modules-visibility';

    public const COMPONENT_CODE = 'component-test-core-base';

    public const NAME_CODE = 'test-class-name';

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
     * A public `$modules` declaration, static or not, typed or not. A
     * property with only `static` or `var` is public too. The modifiers come
     * in any order and case, with comments between them. A `static` right
     * after `protected` or `private` isn't public.
     */
    private const PUBLIC_MODULES =
        '/(?<![\w$])(?<!protected[ \t\n])(?<!private[ \t\n])(?:(?:public|static|var|readonly|final)(?![\w\x80-\xff])'
            . SourceText::GAP
            . ')++(?:\??[\w\\\\|]++'
            . SourceText::GAP
            . ')?\$modules(?![\w\x80-\xff])/i';

    private function __construct() {}

    /**
     * Whether the class text declares a public `$modules`. Most test classes
     * do not, and don't cost a codebase request then.
     */
    public static function mayReport(string $text): bool
    {
        return preg_match(self::PUBLIC_MODULES, $text) === 1;
    }

    /**
     * Where the public `$modules` that the class declares itself is, or null.
     * An inherited or trait property has its name in another file or outside
     * the class, and is the declaring class's business.
     */
    public static function publicModules(?PropertyMetadata $modules, string $path, Span $class): ?SourceLocation
    {
        $location =
            $modules === null || $modules->readVisibility !== Visibility::Public
                ? null
                : $modules->nameLocation ?? $modules->location;

        return $location !== null && $location->file === $path && $class->contains($location->span) ? $location : null;
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
