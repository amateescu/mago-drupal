<?php

/**
 * @file
 * Legacy groups, which mark deprecation scopes in tests.
 */

declare(strict_types=1);

namespace Drupal\Tests\corpus;

use Drupal\corpus\Legacy\RetiredThing;

/**
 * A test covering deprecated behaviour on purpose.
 *
 * @group legacy
 */
final class LegacyGroupTest {

  /**
   * The whole class is in scope, so nothing here is reported.
   */
  public function testAnywhere(): void {
    (new RetiredThing())->stillHere();
  }

}

/**
 * One test method at a time.
 */
final class LegacyMethodTest {

  /**
   * A legacy group on the method covers only this body.
   *
   * @group legacy
   */
  public function testGrouped(): void {
    (new RetiredThing())->stillHere();
  }

  /**
   * An unmarked method next to it is still reported.
   */
  public function testPlain(): void {
    // @mago-expect analysis:deprecated-class
    (new RetiredThing())->stillHere();
  }

}
