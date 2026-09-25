<?php

/**
 * @file
 * Calls that set up a prophecy through ObjectProphecy::__call().
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\corpus\Nested\Thing;
use PHPUnit\Framework\TestCase;

/**
 * Sets up prophecies of the corpus thing.
 */
final class ProphecyCallsTest extends TestCase {

  /**
   * A prophecy documented as a union with the prophesized class.
   *
   * @var \Drupal\corpus\Nested\Thing|\Prophecy\Prophecy\ProphecyInterface
   */
  // @mago-expect analysis:phpunit/prophecy-union
  protected $union;

  /**
   * A prophecy documented as one.
   *
   * @var \Prophecy\Prophecy\ObjectProphecy<\Drupal\corpus\Nested\Thing>
   */
  protected $documented;

  /**
   * Builds the documented prophecy.
   */
  protected function setUp(): void {
    $this->union = $this->prophesize(Thing::class);
    $this->documented = $this->prophesize(Thing::class);
  }

  /**
   * Takes and returns prophecies documented as unions.
   *
   * @param \Drupal\corpus\Nested\Thing|\Prophecy\Prophecy\ObjectProphecy $thing
   *   The prophecy.
   *
   * @return \Drupal\corpus\Nested\Thing|\Prophecy\Prophecy\ProphecyInterface
   *   The same prophecy.
   */
  // @mago-expect analysis:phpunit/prophecy-union(2)
  protected function passUnion($thing) {
    return $thing;
  }

  /**
   * A method the prophesized class declares returns a method prophecy.
   */
  public function testDeclared(): void {
    $thing = $this->prophesize(Thing::class);
    $thing->onlyOnThing()->willReturn(NULL);
    $thing->reveal()->onlyOnThing();
  }

  /**
   * A prophecy documented as one takes the calls that set it up.
   */
  public function testDocumented(): void {
    $this->documented->onlyOnThing()->willReturn(NULL);
    $this->passUnion($this->documented);
  }

  /**
   * A method the class lacks is still reported, as Prophecy throws for it.
   */
  public function testUnknownMethod(): void {
    $thing = $this->prophesize(Thing::class);
    // @mago-expect analysis:non-documented-method
    $thing->notOnThing();
  }

  /**
   * A prophecy told to implement another interface has its methods too.
   */
  public function testWillImplement(): void {
    $chained = $this->prophesize(Thing::class)->willImplement(ThingLabelInterface::class);
    $chained->label()->willReturn('chained');

    $statement = $this->prophesize(Thing::class);
    $statement->willImplement(ThingLabelInterface::class);
    $statement->label()->willReturn('statement');
  }

  /**
   * A double of an interface that is only traversable is an iterator.
   */
  public function testTraversable(): void {
    $list = $this->prophesize(ThingListInterface::class);
    $list->valid()->willReturn(FALSE);
    $list->next();
    $list->rewind();
    $list->current()->willReturn(NULL);
    $list->key()->willReturn(0);
  }

  /**
   * A double of an iterator aggregate gets no iterator methods.
   */
  public function testIteratorAggregate(): void {
    $aggregate = $this->prophesize(ThingAggregateInterface::class);
    // @mago-expect analysis:non-documented-method
    $aggregate->current();
  }

  /**
   * The union is left alone.
   *
   * A prophecy is never a thing, so the docblock is what needs fixing.
   */
  public function testUnion(): void {
    // @mago-expect analysis:non-existent-method
    $this->union->onlyOnThing();
  }

}

/**
 * An interface a prophecy can be told to implement.
 */
interface ThingLabelInterface {

  /**
   * Returns the label.
   */
  public function label(): string;

}

/**
 * An interface that is traversable without saying how.
 *
 * @extends \Traversable<int, mixed>
 */
interface ThingListInterface extends \Traversable {}

/**
 * An interface that is traversable through an iterator aggregate.
 *
 * @extends \IteratorAggregate<int, mixed>
 */
interface ThingAggregateInterface extends \IteratorAggregate {}
