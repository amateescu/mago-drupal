<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\Checks\TestClassCheck;
use PHPUnit\Framework\TestCase;

final class TestClassCheckTest extends TestCase
{
    public function testSkipsWhatNoRuleCanReport(): void
    {
        self::assertFalse(TestClassCheck::mayReport('final class NodeTest extends TestCase {'));
        self::assertFalse(TestClassCheck::mayReport('abstract class NodeTestBase extends TestCase {'));
        self::assertFalse(TestClassCheck::mayReport("#[Group('node')]\nfinal class NodeTest extends TestCase {"));
        self::assertFalse(TestClassCheck::mayReport(
            'final class NodeTest extends TestCase { protected static $modules = [];',
        ));
    }

    public function testKeepsWhatARuleMayReport(): void
    {
        // A concrete class whose name does not end in Test.
        self::assertTrue(TestClassCheck::mayReport('class NodeHelper extends TestCase {'));
        // A public $modules, static, typed or not.
        self::assertTrue(TestClassCheck::mayReport('final class NodeTest extends TestCase { public static $modules;'));
        self::assertTrue(TestClassCheck::mayReport(
            'final class NodeTest extends TestCase { public array $modules = [];',
        ));
        // A declaration the pattern does not read goes on to the lookups.
        self::assertTrue(TestClassCheck::mayReport('enum Suite {'));
    }

    public function testFindsFilesThatMayDeclareComponentTests(): void
    {
        self::assertTrue(TestClassCheck::mayBeComponentTest("<?php\n\nnamespace Drupal\\Tests\\Component\\Utility;\n"));
        self::assertTrue(TestClassCheck::mayBeComponentTest("<?php\n\nnamespace Drupal\\Tests\\Component;\n"));
        self::assertFalse(TestClassCheck::mayBeComponentTest("<?php\n\nnamespace Drupal\\Tests\\Core\\Utility;\n"));
    }

    public function testReportsTheComponentTestThatExtendsACoreBase(): void
    {
        $unit = ['Drupal\Tests\UnitTestCase', 'PHPUnit\Framework\TestCase'];
        self::assertSame('Drupal\Tests\UnitTestCase', TestClassCheck::coreBase(
            'Drupal\Tests\Component\Utility\HtmlTest',
            'Drupal\Tests\UnitTestCase',
            $unit,
        ));
        // Metadata names come lowercased.
        self::assertSame('Drupal\KernelTests\KernelTestBase', TestClassCheck::coreBase(
            'drupal\tests\component\foo\footest',
            'drupal\tests\core\footestbase',
            ['drupal\tests\core\footestbase', 'drupal\kerneltests\kerneltestbase', 'phpunit\framework\testcase'],
        ));
        // WebDriverTestBase is a BrowserTestBase.
        self::assertSame('Drupal\Tests\BrowserTestBase', TestClassCheck::coreBase(
            'Drupal\Tests\Component\Foo\FooTest',
            'Drupal\FunctionalJavascriptTests\WebDriverTestBase',
            ['Drupal\FunctionalJavascriptTests\WebDriverTestBase', 'Drupal\Tests\BrowserTestBase'],
        ));
    }

    public function testLeavesOtherTestsAlone(): void
    {
        $unit = ['Drupal\Tests\UnitTestCase', 'PHPUnit\Framework\TestCase'];
        // PHPUnit's own base.
        self::assertNull(TestClassCheck::coreBase(
            'Drupal\Tests\Component\Utility\HtmlTest',
            'PHPUnit\Framework\TestCase',
            ['PHPUnit\Framework\TestCase'],
        ));
        // A test outside the component tests.
        self::assertNull(TestClassCheck::coreBase('Drupal\Tests\Core\Utility\HtmlTest', $unit[0], $unit));
        self::assertNull(TestClassCheck::coreBase('Drupal\Tests\ComponentExtra\HtmlTest', $unit[0], $unit));
        // The component test base it extends is the one reported.
        self::assertNull(TestClassCheck::coreBase(
            'Drupal\Tests\Component\Utility\HtmlTest',
            'Drupal\Tests\Component\Utility\HtmlTestBase',
            ['Drupal\Tests\Component\Utility\HtmlTestBase', ...$unit],
        ));
    }
}
