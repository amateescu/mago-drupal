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
}
