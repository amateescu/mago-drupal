<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * A class with a method named after it and no constructor.
 */
class OldStyleWidget {

  /**
   * PHP doesn't run it as the constructor.
   */
  // @mago-expect lint:drupal/old-style-constructor
  public function OldStyleWidget(): void {
  }

}

/**
 * The names are compared without regard to case.
 */
class OldStyleLink {

  /**
   * Builds the link.
   */
  // @mago-expect lint:drupal/old-style-constructor
  public function oldstylelink(): string {
    return '';
  }

}

/**
 * An abstract method counts.
 */
abstract class OldStyleBase {

  /**
   * An abstract method named after the class.
   */
  // @mago-expect lint:drupal/old-style-constructor
  abstract public function oldStyleBase(): void;

}

/**
 * A constructor of its own makes the method an ordinary one.
 */
class OldStyleBoth {

  /**
   * The constructor, in another case.
   */
  public function __CONSTRUCT() {}

  /**
   * Fine, since the class has a constructor.
   */
  public function oldStyleBoth(): void {
  }

}

/**
 * The constructor of an anonymous class in a method doesn't count.
 */
class OldStyleNested {

  /**
   * Named after the class.
   */
  // @mago-expect lint:drupal/old-style-constructor
  public function oldStyleNested(): object {
    return new class() {

      /**
       * The constructor of the anonymous class.
       */
      public function __construct() {}

    };
  }

}

/**
 * Coder checks classes only.
 */
interface OldStyleInterface {

  /**
   * Fine in an interface.
   */
  public function oldStyleInterface(): void;

}

/**
 * Coder checks classes only.
 */
trait OldStyleTrait {

  /**
   * Fine in a trait.
   */
  public function oldStyleTrait(): void {
  }

}
