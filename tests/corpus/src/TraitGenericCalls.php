<?php

/**
 * @file
 * Calls from a trait to the generic methods of the tests that use it.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\corpus\Nested\Thing;
use PHPUnit\Framework\TestCase;

/**
 * Creates mocks for the tests that use it.
 */
trait ThingMockingTrait {

  /**
   * A class name fills in the template of the method.
   */
  public function mocks(): void {
    $this->createMock(Thing::class)->onlyOnThing();
    $this->createMock('Drupal\corpus\Nested\Thing')->onlyOnThing();
    $this->createStub(Thing::class)->onlyOnThing();
    $this->prophesize(Thing::class)->reveal()->onlyOnThing();
    // @mago-expect analysis:non-existent-method
    $this->createMock(Thing::class)->missing();
  }

  /**
   * A name that is only known when the code runs leaves the result mixed.
   */
  public function dynamic(string $class): void {
    // @mago-expect analysis:mixed-method-access
    $this->createMock($class)->onlyOnThing();
    // @mago-expect analysis:mixed-method-access
    $this->createMock('Drupal\corpus\Nested\NoSuchThing')->onlyOnThing();
  }

}

/**
 * Has the methods that the trait calls.
 */
final class ThingMockingTest extends TestCase {

  use ThingMockingTrait;

}
