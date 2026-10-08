<?php

/**
 * @file
 * Calls and properties on a trait's `$this` that the classes using it provide.
 */

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Relies on methods the classes using it declare.
 */
trait LabelledTrait {

  /**
   * Every class has label() and suffix(), so the calls have their types.
   */
  public function shout(): string {
    return strtoupper($this->label()) . static::suffix();
  }

  /**
   * The classes agree on the parameter type, so the argument is checked.
   */
  public function countWrong(): int {
    // @mago-expect analysis:invalid-argument
    return $this->count('three');
  }

  /**
   * One class lacks the method, so the call stays a missing method.
   */
  public function partial(): void {
    // @mago-expect analysis:non-existent-method
    $this->onlyInFirst();
  }

  /**
   * Every class has the property, so reading it is not reported.
   */
  public function prefix(): string {
    return (string) $this->prefix;
  }

  /**
   * One class lacks the property, so the read stays a missing property.
   */
  public function partialProperty(): mixed {
    // @mago-expect analysis:non-existent-property
    return $this->firstOnly;
  }

}

/**
 * Declares everything the trait calls.
 */
final class FirstLabelled {

  use LabelledTrait;

  /**
   * The prefix.
   */
  public string $prefix = 'first:';

  /**
   * Exists on this class only.
   */
  public int $firstOnly = 1;

  /**
   * Returns the label.
   */
  public function label(): string {
    return 'first';
  }

  /**
   * Counts up by the given amount.
   */
  public function count(int $by): int {
    return $by;
  }

  /**
   * Returns the suffix.
   */
  public static function suffix(): string {
    return '!';
  }

  /**
   * Exists on this class only.
   */
  public function onlyInFirst(): void {
  }

}

/**
 * Provides the label to the class below.
 */
abstract class LabelledBase {

  /**
   * The prefix.
   */
  protected string $prefix = 'second:';

  /**
   * Returns the label.
   */
  public function label(): string {
    return 'second';
  }

}

/**
 * Gets the label from its parent.
 */
final class SecondLabelled extends LabelledBase {

  use LabelledTrait;

  /**
   * Counts up by the given amount.
   */
  public function count(int $by): int {
    return $by * 2;
  }

  /**
   * Returns the suffix.
   */
  public static function suffix(): string {
    return '?';
  }

}

/**
 * Leaves its first method to the class using it.
 */
trait ProvidedTrait {

  /**
   * The class declares this one, so the trait does not declare its own.
   */
  abstract public function aProvided(): string;

  /**
   * The class has format(), so the call has its type.
   */
  public function render(): string {
    return strtoupper($this->format());
  }

}

/**
 * Implements the trait's first method.
 */
final class ProvidingClass {

  use ProvidedTrait;

  /**
   * Returns the first part.
   */
  public function aProvided(): string {
    return 'a';
  }

  /**
   * Returns the formatted text.
   */
  public function format(): string {
    return 'b';
  }

}

/**
 * Reads a property that no class provides.
 */
trait UnusedTrait {

  /**
   * No class uses the trait, so nothing vouches for the property.
   */
  public function unused(): mixed {
    // @mago-expect analysis:non-existent-property
    return $this->nowhere;
  }

}

/**
 * Calls a method its users declare with different signatures.
 */
trait ArityTrait {

  /**
   * A call has to fit every user's declaration.
   */
  public function callHelpers(): void {
    // @mago-expect analysis:too-many-arguments
    $this->helper(1, 'two');
    // @mago-expect analysis:too-few-arguments
    $this->other();
    $this->helper(1);
    // @mago-expect analysis:too-few-arguments
    $this->joined();
    $this->joined('-', 'a', 'b');
    // @mago-expect analysis:too-few-arguments
    $this->spread();
    $this->spread('a', 'b');
    // @mago-expect analysis:too-few-arguments
    $this->collide('a');
    $this->collide('a', 'b');
    // Both users are variadic, and the longer one needs two arguments.
    // @mago-expect analysis:too-few-arguments
    $this->varied(1);
    $this->varied(1, 2, 3);
  }

}

/**
 * Takes an optional second argument; sorts first.
 */
final class AAritySpacious {

  use ArityTrait;

  /**
   * Takes one or two arguments.
   */
  public function helper(int $a, string $b = ''): string {
    return $a . $b;
  }

  /**
   * Takes an optional argument.
   */
  public function other(int $optional = 0): int {
    return $optional;
  }

  /**
   * Joins the parts.
   */
  public function joined(string $glue, string ...$parts): string {
    return implode($glue, $parts);
  }

  /**
   * Takes any number of strings, where the other user takes one or two.
   */
  public function spread(string ...$all): string {
    return implode('', $all);
  }

  /**
   * A variadic named like the fallback name of a later position.
   */
  public function collide(string ...$arg1): string {
    return implode('', $arg1);
  }

  /**
   * Takes any number of integers.
   */
  public function varied(int ...$numbers): int {
    return array_sum($numbers);
  }

}

/**
 * Takes only what it needs.
 */
final class BArityStrict {

  use ArityTrait;

  /**
   * Takes one argument.
   */
  public function helper(int $a): string {
    return (string) $a;
  }

  /**
   * Takes one required argument.
   */
  public function other(int $required): int {
    return $required;
  }

  /**
   * Joins the pieces.
   */
  public function joined(string $glue, string ...$pieces): string {
    return implode($glue, $pieces);
  }

  /**
   * Takes one or two strings.
   */
  public function spread(string $first, string $second = ''): string {
    return $first . $second;
  }

  /**
   * Takes exactly two strings.
   */
  public function collide(string $a, string $b): string {
    return $a . $b;
  }

  /**
   * Takes two integers or more.
   */
  public function varied(int $first, int $second, int ...$rest): int {
    return $first + $second + array_sum($rest);
  }

}

/**
 * Reads properties as static ones and as instance ones.
 */
trait StaticPropertyTrait {

  /**
   * Every class has a static `$cache`, so the read is not reported.
   */
  public static function cached(): mixed {
    return static::$cache;
  }

  /**
   * A static read reaches neither an instance nor a magic property.
   *
   * One class declares the property as an instance one and the other as a
   * magic one, so the read stays a missing property.
   */
  public static function value(): mixed {
    // @mago-expect analysis:non-existent-property
    return self::$value;
  }

  /**
   * An instance read does not reach a static property either.
   */
  public function instanceCache(): mixed {
    // @mago-expect analysis:non-existent-property
    return $this->cache;
  }

}

/**
 * Declares the value as an instance property.
 */
final class StaticPropertyFirst {

  use StaticPropertyTrait;

  /**
   * The cache.
   *
   * @var array<string, string>
   */
  public static array $cache = [];

  /**
   * The value.
   */
  public string $value = '';

}

/**
 * Has the value as a magic property.
 *
 * @property string $value
 */
final class StaticPropertySecond {

  use StaticPropertyTrait;

  /**
   * The cache.
   *
   * @var array<string, string>
   */
  public static array $cache = [];

  /**
   * Returns no magic property.
   */
  public function __get(string $name): mixed {
    return NULL;
  }

}

/**
 * Calls the classes' methods through `self::` and `static::`.
 */
trait ScopedCallTrait {

  /**
   * An instance method has `$this`, so the call reaches label().
   */
  public function scopedLabel(): string {
    return static::label();
  }

  /**
   * Every class declares create() as static, so the call has its type.
   */
  public static function make(): string {
    return static::create();
  }

  /**
   * A static method has no `$this`, so the call cannot reach label().
   */
  public static function staticLabel(): void {
    // @mago-expect analysis:drupal/trait-non-static-call
    self::label();
    // A closure in a static method has no `$this` either.
    $call = static function (): void {
      // @mago-expect analysis:drupal/trait-non-static-call
      static::label();
    };
    $call();
  }

}

/**
 * Declares label() as an instance method and create() as a static one.
 */
final class ScopedFirst {

  use ScopedCallTrait;

  /**
   * Returns the label.
   */
  public function label(): string {
    return 'first';
  }

  /**
   * Creates the label.
   */
  public static function create(): string {
    return 'first';
  }

}

/**
 * Declares the same methods as the class above.
 */
final class ScopedSecond {

  use ScopedCallTrait;

  /**
   * Returns the label.
   */
  public function label(): string {
    return 'second';
  }

  /**
   * Creates the label.
   */
  public static function create(): string {
    return 'second';
  }

}
