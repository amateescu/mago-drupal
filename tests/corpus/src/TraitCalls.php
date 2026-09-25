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
