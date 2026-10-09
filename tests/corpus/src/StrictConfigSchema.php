<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Turns the check off with FALSE.
 */
class DisabledTest {

  // @mago-expect lint:drupal/strict-config-schema
  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema = FALSE;

}

/**
 * Turns the check off with NULL.
 */
class NullTest {

  // @mago-expect lint:drupal/strict-config-schema
  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema = NULL;

}

/**
 * Leaves the check without a value.
 */
class NoDefaultTest {

  // @mago-expect lint:drupal/strict-config-schema
  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema;

}

/**
 * Sets a value that is not a boolean.
 */
class ArrayTest {

  // @mago-expect lint:drupal/strict-config-schema
  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  public static array $strictConfigSchema = [];

}

/**
 * Declares the flag next to another property.
 */
class ListTest {

  // @mago-expect lint:drupal/strict-config-schema
  /**
   * The flags.
   *
   * @var mixed
   */
  protected $other = TRUE, $strictConfigSchema = FALSE;

}

/**
 * Keeps the check on.
 */
class EnabledTest {

  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema = TRUE;

}

/**
 * The first of TRUE, FALSE and NULL decides, as in Coder.
 */
class FirstKeywordTest {

  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema = [TRUE, FALSE];

}

/**
 * Holds another property.
 */
class OtherPropertyTest {

  /**
   * A property of another name.
   *
   * @var mixed
   */
  protected $strictSchema = FALSE;

  // @mago-expect lint:drupal/property-name
  /**
   * Another case is another name.
   *
   * @var mixed
   */
  protected $StrictConfigSchema = FALSE;

}

/**
 * Is not a test class, because Test is not a word of the name.
 */
class TestimonialBlock {

  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema = FALSE;

}

/**
 * Is not a test class either.
 */
class Contester {

  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema = FALSE;

}

/**
 * Is a test trait.
 */
trait DisabledTestTrait {

  // @mago-expect lint:drupal/strict-config-schema
  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema = FALSE;

}

/**
 * Is a base class of tests.
 */
abstract class KernelTestBase {

  // @mago-expect lint:drupal/strict-config-schema
  /**
   * The strict config schema flag.
   *
   * @var mixed
   */
  protected $strictConfigSchema = FALSE;

}

/**
 * Holds an anonymous class that belongs to a test class.
 */
class AnonymousTest {

  /**
   * Builds the object.
   */
  public function build(): object {
    return new class() {

      // @mago-expect lint:drupal/strict-config-schema
      /**
       * The strict config schema flag.
       *
       * @var mixed
       */
      protected $strictConfigSchema = FALSE;

    };
  }

}

/**
 * Is not a test class, and neither is its anonymous class.
 */
class AnonymousHelper {

  /**
   * Builds the object.
   */
  public function build(): object {
    return new class() {

      /**
       * The strict config schema flag.
       *
       * @var mixed
       */
      protected $strictConfigSchema = FALSE;

    };
  }

}

/**
 * Has a local variable of the same name.
 */
class LocalVariableTest {

  /**
   * Reads the flag.
   */
  public function read(): bool {
    $strictConfigSchema = FALSE;

    return $strictConfigSchema;
  }

}
