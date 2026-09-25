<?php

/**
 * @file
 * Mocks documented as a union of the mocked type and MockObject.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\corpus\Nested\Thing;
use PHPUnit\Framework\TestCase;

/**
 * Configures doubles through the unions older tests document them with.
 */
final class MockUnionsTest extends TestCase {

  /**
   * A mock, documented as a union.
   *
   * @var \Drupal\corpus\Nested\Thing|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $thing;

  /**
   * A stub, which takes no expectations.
   *
   * @var \Drupal\corpus\Nested\Thing|\PHPUnit\Framework\MockObject\Stub
   */
  protected $stub;

  /**
   * A mock documented as the mocked type alone.
   *
   * @var \Drupal\corpus\Nested\Thing
   */
  protected $plain;

  /**
   * Builds the doubles.
   */
  protected function setUp(): void {
    $this->thing = $this->createMock(Thing::class);
    $this->stub = $this->createStub(Thing::class);
    $this->plain = $this->createMock(Thing::class);
  }

  /**
   * The mocked half takes the MockObject methods, so the chain keeps its type.
   */
  public function testConfigured(): void {
    $this->thing->expects($this->once())->method('onlyOnThing')->willReturn(NULL);
    $this->thing->method('onlyOnThing')->willReturn(NULL);
  }

  /**
   * A method the mocked type lacks is still missing on it.
   */
  public function testUnknownMethod(): void {
    // @mago-expect analysis:non-existent-method
    $this->thing->method('notOnThing');
  }

  /**
   * A mock typed as the mocked class alone is reported once per call.
   */
  public function testPlainType(): void {
    // @mago-expect analysis:phpunit/mock-call-on-plain-type
    $this->plain->expects($this->once())->method('onlyOnThing')->willReturn(NULL);
    // @mago-expect analysis:phpunit/mock-call-on-plain-type
    $this->plainHelper()->method('onlyOnThing')->willReturn(NULL);
    // @mago-expect analysis:non-existent-method
    $this->plain->method('notOnThing');
  }

  /**
   * A mock that keeps its intersection type is fine.
   */
  public function testIntersection(): void {
    $mock = $this->createMock(Thing::class);
    $mock->expects($this->once())->method('onlyOnThing')->willReturn(NULL);
  }

  /**
   * Returns a mock under the mocked class's type.
   */
  private function plainHelper(): Thing {
    return $this->createMock(Thing::class);
  }

  /**
   * The stub half of the union has no `expects()`.
   */
  public function testExpectationOnStub(): void {
    // @mago-expect analysis:non-existent-method
    $this->stub->expects($this->once());
  }

}
