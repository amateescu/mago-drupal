<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/global-constant
const NOT_PREFIXED_IN_A_PHP_FILE = 1;

/**
 * Holds define() calls that the name rule reads in every kind of file.
 */
class DefineNameFixture {

  /**
   * Defines constants with names that are not upper case.
   */
  public function lowerCase(): void {
    // @mago-expect lint:drupal/define-name
    define('lower_name', 1);
    // @mago-expect lint:drupal/define-name
    define('Mixed_Name', 1);
    // @mago-expect lint:drupal/define-name
    define('_LEADING_UNDERSCORE_lower', 1);
    // @mago-expect lint:drupal/define-name
    define('lower_café', 1);
    // @mago-expect lint:drupal/define-name
    define(constant_name: 'lower_named', value: 1);
  }

  /**
   * Writes the call in another way.
   */
  public function otherSpellings(): void {
    // @mago-expect lint:drupal/define-name
    \define('lower_fully_qualified', 1);
    // @mago-expect lint:drupal/define-name
    DEFINE('lower_upper_case_call', 1);
    // @mago-expect lint:drupal/define-name
    define('lower_double_quoted', 1);
  }

  /**
   * Only the part after the last backslash must be upper case.
   */
  public function namespacedNames(): void {
    // @mago-expect lint:drupal/define-name
    define('Drupal\\corpus\\lower_name', 1);
    define('drupal\\corpus\\UPPER_NAME', 1);
  }

  /**
   * Checks the whole name when it is built from literals.
   */
  public function concatenation(string $suffix): void {
    // @mago-expect lint:drupal/define-name
    define('lower_' . 'tail', 1);
    // @mago-expect lint:drupal/define-name
    define('UPPER_' . 'lower_tail', 1);
    define('UPPER_' . 'TAIL', 1);
    define('lower_' . $suffix, 1);
  }

  /**
   * Names that are fine.
   */
  public function fine(string $name): void {
    define('UPPER_NAME', 1);
    define('UPPER_123', 1);
    define('UPPER_CAFÉ', 1);
    define('', 1);
    define($name, 1);
    define("lower_$name", 1);
    define(self::NAME, 1);
  }

  public const NAME = 'lower_constant';

  /**
   * Defines a method that has the name of the function.
   */
  public function define(string $name, int $value): void {
  }

  /**
   * Calls the method, which is not the function.
   */
  public function methodCalls(?self $other): void {
    $this->define('lower_method', 1);
    $other?->define('lower_nullsafe', 1);
    self::define('lower_static', 1);
  }

  /**
   * Reads define as a name in other places.
   */
  public function notCalls(): string {
    $object = new Define('lower_new');

    return $object::class . (defined('lower_defined') ? 'yes' : 'no');
  }

}

/**
 * A class that is named Define.
 */
class Define {

  /**
   * Builds the object.
   */
  public function __construct(string $name) {}

}
