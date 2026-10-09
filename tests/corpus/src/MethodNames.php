<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Methods whose names start with two underscores.
 */
class MethodNames {

  /**
   * A name that is not a PHP magic method.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __probe(): void {
  }

  /**
   * A static method with a name that is not magic.
   */
  // @mago-expect lint:drupal/method-name-underscore
  private static function __secret(): void {
  }

  /**
   * An underscore inside the rest of the name.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __call_static(): void {
  }

  /**
   * A digit after the underscores.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __1abc(): void {
  }

  /**
   * Starts like a magic method but has more letters.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __toStringAll(): void {
  }

  /**
   * Not a magic method, though PHP once had a global function of this name.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __autoload(): void {
  }

  /**
   * A reference return.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function &__refReturn(): array {
    $value = [];

    return $value;
  }

  /**
   * A method with an attribute.
   */
  // @mago-expect lint:drupal/method-name-underscore
  #[\ReturnTypeWillChange]
  public function __attributed(): void {
  }

  /**
   * An anonymous class inside a method.
   */
  public function anonymous(): object {
    return new class() {

      /**
       * A name that is not magic.
       */
      // @mago-expect lint:drupal/method-name-underscore
      public function __anonymousProbe(): void {
      }

    };
  }

  /**
   * One underscore is the other check of the rule.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function _single(): void {
  }

  /**
   * Three underscores are fine.
   */
  public function ___triple(): void {
  }

  /**
   * Underscores inside or at the end of the name are fine.
   */
  public function get__value(): void {
  }

  /**
   * Only underscores are fine.
   */
  public function __(): void {
  }

  /**
   * A function in a method body is no method.
   */
  public function nested(): void {
    /**
     * A function, not a method.
     */
    function __nested_in_method(): void {
    }
  }

  /**
   * Magic methods.
   */
  public function __invoke(): void {
  }

  /**
   * Magic methods are matched without regard to case.
   */
  public function __DEBUGINFO(): array {
    return [];
  }

  /**
   * The methods of the SOAP client.
   */
  public function __getLastRequestHeaders(): void {
  }

  /**
   * A magic method name in a string is no declaration.
   */
  public function text(): string {
    return 'public function __probe() {}';
  }

}

/**
 * An interface method.
 */
interface MethodNamesInterface {

  /**
   * A name that is not magic.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __ifaceMethod(): void;

  /**
   * A magic name.
   */
  public function __toString(): string;

}

/**
 * A trait method.
 */
trait MethodNamesTrait {

  /**
   * A name that is not magic.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __traitMethod(): void {
  }

}

/**
 * An enum method.
 */
enum MethodNamesEnum {

  case One;

  /**
   * A name that is not magic.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __enumMethod(): void {
  }

}
