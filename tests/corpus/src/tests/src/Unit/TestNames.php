<?php

declare(strict_types=1);

namespace Drupal\Tests\corpus\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * A base whose name doesn't end in Test, TestBase or TestCase.
 */
abstract class CorpusUnitBase extends UnitTestCase {}

/**
 * The linter rule can't tell from the parent's name that this is a test.
 */
// @mago-expect analysis:drupal/test-class-name
final class ViaBaseChecks extends CorpusUnitBase {}

/**
 * The linter rule reports this one, so the check leaves it alone.
 */
// @mago-expect lint:drupal/test-class-suffix
final class DirectChecks extends UnitTestCase {}

/**
 * A fixture that a class below extends. It isn't a test base.
 */
class FixtureTestBase {}

/**
 * Extends a fixture declared in this file, so it isn't a test.
 */
final class FixtureChild extends FixtureTestBase {}

/**
 * A test base in this file, on a base whose name says nothing.
 */
abstract class LocalTestBase extends CorpusUnitBase {}

/**
 * The linter rule takes LocalTestBase for a fixture, so the check reports.
 */
// @mago-expect analysis:drupal/test-class-name
final class LocalChecks extends LocalTestBase {}

/**
 * A test base in this file, on a test base.
 */
abstract class SharedTestBase extends UnitTestCase {}

/**
 * The linter rule reports this one.
 */
// @mago-expect lint:drupal/test-class-suffix
final class SharedChecks extends SharedTestBase {}

/**
 * Named right, on a base whose name says nothing.
 */
final class ViaBaseTest extends CorpusUnitBase {}
