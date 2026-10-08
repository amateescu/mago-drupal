<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\TestFiles;
use PHPUnit\Framework\TestCase;

final class TestFilesTest extends TestCase
{
    public function testMatchesATestsSegmentOnly(): void
    {
        self::assertTrue(TestFiles::isTest('web/modules/contrib/trash/tests/src/Kernel/TrashTest.php'));
        self::assertTrue(TestFiles::isTest('web/core/tests/Drupal/Tests/Core/Block/BlockManagerTest.php'));
        self::assertFalse(TestFiles::isTest('web/modules/contrib/trash/src/TrashManager.php'));
        self::assertFalse(TestFiles::isTest('src/Container.php'));
        self::assertFalse(TestFiles::isTest('web/modules/custom/testsuite/src/Runner.php'));
    }

    public function testCountsHookDocumentationWithTests(): void
    {
        self::assertTrue(TestFiles::isTestOrHookDocumentation(
            'web/modules/contrib/trash/tests/src/Kernel/TrashTest.php',
        ));
        self::assertTrue(TestFiles::isTestOrHookDocumentation('web/modules/contrib/trash/trash.api.php'));
        self::assertFalse(TestFiles::isTestOrHookDocumentation('web/modules/contrib/trash/trash.module'));
    }
}
