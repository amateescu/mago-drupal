<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\TestClassSuffix;
use PHPUnit\Framework\TestCase;

final class TestClassSuffixTest extends TestCase
{
    public function testLeavesTheSuffixOfLintedTestsToTheLinter(): void
    {
        $unit = 'modules/node/tests/src/Unit/NodeChecks.php';
        // Named right, or abstract.
        self::assertFalse(TestClassSuffix::mayBeMisnamed('final class NodeTest extends Base {', $unit, ''));
        self::assertFalse(TestClassSuffix::mayBeMisnamed('abstract class NodeBase extends TestCase {', $unit, ''));
        self::assertFalse(TestClassSuffix::mayBeMisnamed(
            'readonly abstract class NodeBase extends TestCase {',
            'modules/node/src/NodeBase.php',
            '',
        ));
        // The linter rule takes these for tests.
        self::assertFalse(TestClassSuffix::mayBeMisnamed('final class NodeChecks extends UnitTestCase {', $unit, ''));
        self::assertFalse(TestClassSuffix::mayBeMisnamed(
            "#[Group('node')]\nfinal class NodeChecks extends \\Drupal\\Tests\\UnitTestCase {",
            'web/core/tests/Drupal/Tests/Core/NodeChecks.php',
            '',
        ));
        // A parent declared in the same file counts when it extends a test
        // base.
        self::assertFalse(TestClassSuffix::mayBeMisnamed(
            'class NodeChecks extends NodeTestBase {',
            $unit,
            "<?php\n\nabstract class NodeTestBase extends UnitTestCase {}\n\nclass NodeChecks extends NodeTestBase {}\n",
        ));
    }

    public function testKeepsTheSuffixTheLinterCannotSee(): void
    {
        $unit = 'modules/node/tests/src/Unit/NodeChecks.php';
        // Outside the test directories.
        self::assertTrue(TestClassSuffix::mayBeMisnamed(
            'class NodeChecks extends TestCase {',
            'modules/node/src/Testing/NodeChecks.php',
            '',
        ));
        self::assertTrue(TestClassSuffix::mayBeMisnamed(
            'final class NodeChecks extends KernelTestBase {',
            'modules/node/tests/src/NodeChecks.php',
            '',
        ));
        // A parent whose name doesn't say anything.
        self::assertTrue(TestClassSuffix::mayBeMisnamed('class NodeChecks extends NodeUnitBase {', $unit, ''));
        // A parent declared in the same file with no test base of its own,
        // in any letter case. A docblock that names it doesn't count.
        $contents = "<?php\n\n/**\n * The class NodeTestBase extends UnitTestCase.\n */\nCLASS NodeTestBase {}\n";
        self::assertTrue(TestClassSuffix::mayBeMisnamed('class NodeChecks extends NodeTestBase {', $unit, $contents));
        self::assertTrue(TestClassSuffix::mayBeMisnamed(
            'class NodeChecks extends NodeTestBase {',
            $unit,
            "<?php\n\nclass NodeTestBase extends Helper {}\n",
        ));
        // Comments between the tokens of the declarations.
        self::assertTrue(TestClassSuffix::mayBeMisnamed(
            "final /* Checks. */ class NodeChecks # Base.\n extends TestCase {",
            'modules/node/src/Testing/NodeChecks.php',
            '',
        ));
        self::assertTrue(TestClassSuffix::mayBeMisnamed(
            'class NodeChecks extends NodeTestBase {',
            $unit,
            "<?php\n\nclass /* A helper. */ NodeTestBase {}\n",
        ));
    }

    public function testReadsTheParentAsWritten(): void
    {
        $contents = "<?php\n\nclass NodeTestBase {}\n";
        // A parent with a namespace is never the fixture in this file.
        self::assertTrue(TestClassSuffix::testParent($contents, 'Drupal\Tests\node\NodeTestBase'));
        self::assertFalse(TestClassSuffix::testParent($contents, 'NodeTestBase'));
        self::assertFalse(TestClassSuffix::testParent('', 'NodeHelper'));
    }
}
