<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Method names in and out of lowerCamelCase.
 */
class MethodCamelCaps {

  /**
   * Two capitals in a row.
   */
  // @mago-expect lint:drupal/method-name
  public function getURL(): void {
  }

  /**
   * An acronym written as a word is fine.
   */
  public function loadUrl(): void {
  }

  /**
   * An underscore inside the name.
   */
  // @mago-expect lint:drupal/method-name
  public function get_url(): void {
  }

  /**
   * A capital first letter.
   */
  // @mago-expect lint:drupal/method-name
  public static function SetUrl(): void {
  }

  /**
   * A single capital.
   */
  // @mago-expect lint:drupal/method-name
  protected function R(): void {
  }

  /**
   * A single lower-case letter is fine.
   */
  protected function x(): void {
  }

  /**
   * A capital after a digit is fine.
   */
  public function a1B(): void {
  }

  /**
   * A digit between two capitals ends the run, so this is fine.
   */
  public function getA2B(): void {
  }

  /**
   * Capitals after a digit.
   */
  // @mago-expect lint:drupal/method-name
  public function get2FA(): void {
  }

  /**
   * Capitals before a digit.
   */
  // @mago-expect lint:drupal/method-name
  public function getHTTP2Client(): void {
  }

  /**
   * A letter outside ASCII.
   */
  // @mago-expect lint:drupal/method-name
  public function getÜber(): void {
  }

  /**
   * No visibility keyword, Coder's NotCamelCaps code.
   */
  // @mago-expect lint:drupal/method-visibility
  // @mago-expect lint:drupal/method-name
  function noScope_name(): void {
  }

  /**
   * A leading underscore, then a name that is fine.
   *
   * The underscore is reported by drupal/method-name-underscore. This rule
   * strips it.
   */
  // @mago-expect lint:drupal/method-name-underscore
  protected function _helper(): void {
  }

  /**
   * Both checks.
   */
  // @mago-expect lint:drupal/method-name-underscore
  // @mago-expect lint:drupal/method-name
  protected function _helper_two(): void {
  }

  /**
   * A double underscore that isn't magic, then a name that is fine.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function __probe(): void {
  }

  /**
   * Three underscores, then a name that isn't camel case.
   */
  // @mago-expect lint:drupal/method-name
  public function ___Probe(): void {
  }

  /**
   * Magic methods are fine.
   */
  public function __toString(): string {
    return '';
  }

  /**
   * So are the methods of the SOAP client, in any case.
   */
  public function __SOAPCALL(): void {
  }

  /**
   * An attribute between the docblock and the name.
   */
  // @mago-expect lint:drupal/method-name
  #[\ReturnTypeWillChange]
  public function getID(): void {
  }

  /**
   * A function in a method body is no method.
   */
  public function nested(): void {
    /**
     * A function, not a method.
     */
    function method_camel_caps_Nested(): void {
    }
  }

  /**
   * A method of an anonymous class inside a method.
   */
  public function anonymous(): object {
    return new class() {

      /**
       * Not camel case.
       */
      // @mago-expect lint:drupal/method-name
      public function do_it(): void {
      }

    };
  }

}

/**
 * A class with PHP 4 style constructor and destructor names.
 *
 * Coder skips them. The class has a constructor of its own.
 */
class LegacyWidget {

  /**
   * The constructor.
   */
  public function __construct() {}

  /**
   * Named after the class.
   */
  public function LegacyWidget(): void {
  }

  /**
   * Named after the class with an underscore, in another case.
   */
  // @mago-expect lint:drupal/method-name-underscore
  public function _legacywidget(): void {
  }

}

/**
 * An interface method.
 */
interface MethodCamelCapsInterface {

  /**
   * Not camel case.
   */
  // @mago-expect lint:drupal/method-name
  public function do_it(): void;

}

/**
 * A trait method.
 */
trait MethodCamelCapsTrait {

  /**
   * Two capitals in a row.
   */
  // @mago-expect lint:drupal/method-name
  public function doIT(): void {
  }

}

/**
 * An enum method.
 */
enum MethodCamelCapsEnum {

  case One;

  /**
   * Not camel case.
   */
  // @mago-expect lint:drupal/method-name
  public function do_it(): void {
  }

}
