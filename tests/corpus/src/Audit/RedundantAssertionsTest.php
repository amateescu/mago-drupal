<?php

declare(strict_types=1);

// Assertions that always pass, given the type of the value.
namespace Drupal\corpus\Audit;

use Drupal\corpus\Nested\Thing;
use PHPUnit\Framework\TestCase;

/**
 * Asserts what the types already say, next to assertions that can fail.
 */
final class RedundantAssertionsTest extends TestCase {

  /**
   * The thing under test.
   */
  protected Thing $thing;

  /**
   * A thing only a docblock types, so it is NULL until something sets it.
   *
   * @var \Drupal\corpus\Nested\Thing
   */
  protected $documented;

  /**
   * A thing some call may set or clear.
   *
   * @var \Drupal\corpus\Nested\Thing|null
   */
  protected $found = NULL;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->thing = new Thing();
    $this->documented = new Thing();
  }

  /**
   * Each of these can only pass.
   *
   * @param \Drupal\corpus\Nested\Thing $thing
   *   A thing.
   * @param string $name
   *   A name.
   * @param array $items
   *   Some items.
   */
  public function testAlwaysPasses(Thing $thing, string $name, array $items): void {
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertInstanceOf(Thing::class, $thing);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertNotNull($thing);
    // @mago-expect analysis:phpunit/redundant-assertion
    self::assertIsString($name);
    // @mago-expect analysis:phpunit/redundant-assertion
    static::assertIsArray($items);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertTrue(TRUE, 'No exception.');
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertNotFalse($name);
  }

  // @mago-format-ignore-start
  /**
   * A comment between the method name and its parenthesis.
   *
   * @param \Drupal\corpus\Nested\Thing $thing
   *   A thing.
   */
  public function testCommentBeforeParenthesis(Thing $thing): void {
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertNotNull /* Reason. */ ($thing);
  }
  // @mago-format-ignore-end

  /**
   * Each of these can fail.
   *
   * @param \Drupal\corpus\Nested\Thing|null $maybe
   *   A thing, or nothing.
   * @param bool $flag
   *   A flag.
   * @param object $any
   *   Any object.
   * @param mixed $value
   *   Anything.
   */
  public function testCanFail(?Thing $maybe, bool $flag, object $any, mixed $value): void {
    $this->assertInstanceOf(Thing::class, $any);
    $this->assertNotNull($maybe);
    // The value is the second argument here, not the first.
    $this->assertNotNull(message: 'Named out of order.', actual: $maybe);
    $this->assertTrue($flag);
    $this->assertNotFalse($flag);
    $this->assertIsString($value);
  }

  /**
   * A property counts only as far as its declaration goes.
   */
  public function testProperties(): void {
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertNotNull($this->thing);
    // PHP does not check a docblock type, so the assertion is the only check.
    $this->assertNotNull($this->documented);
    $this->found = new Thing();
    $this->thing->onlyOnThing();
    // Declared nullable, and the call above may have cleared it.
    $this->assertNotNull($this->found);
    $items = ['a' => 1];
    // An isset() tests __isset() or ArrayAccess, which types do not describe.
    $this->assertTrue(isset($items['a']));
    // The same with a negation in front.
    $this->assertFalse(!isset($items['a']));
    // Mago's ArrayAccess stub leaves out the null an empty list returns.
    $this->assertNotNull($items['a']);
  }

  /**
   * An inline docblock type is the test's claim, which the assertion checks.
   */
  public function testDocumentedVariable(): void {
    /** @var \Drupal\corpus\Nested\Thing $thing */
    $thing = $this->findThing();
    $this->assertNotNull($thing);
  }

  /**
   * Finds the thing a call may have set.
   */
  private function findThing(): ?Thing {
    return $this->found;
  }

  /**
   * Another receiver's property does not count.
   *
   * Only `$this->name` is looked up, even with a property of that name on
   * the test class.
   */
  public function testOtherReceiver(): void {
    $other = new \stdClass();
    $other->thing = new Thing();
    $this->assertNotNull($other->thing);
  }

  /**
   * The assertions that name the one value their type allows.
   */
  public function testOneValue(): void {
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertNull(NULL);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertFalse(FALSE);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertNotTrue(FALSE);
  }

  /**
   * One pin for each type assertion.
   *
   * @param int $number
   *   A number.
   * @param float $fraction
   *   A fraction.
   */
  public function testTypes(int $number, float $fraction): void {
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertIsBool(TRUE);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertIsFloat($fraction);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertIsInt($number);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertIsNumeric($number);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertIsObject($this->thing);
    // @mago-expect analysis:phpunit/redundant-assertion
    $this->assertIsScalar($number);
  }

  /**
   * A `static` or `global` variable keeps its value across calls.
   */
  public function testSharedVariables(): void {
    static $seen = NULL;
    global $_corpus_flag;
    $this->assertNull($seen);
    $seen = 1;
    $this->assertNull($_corpus_flag);
  }

}
