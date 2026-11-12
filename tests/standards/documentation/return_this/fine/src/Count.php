<?php

namespace Drupal\example;

/**
 * Holds a count.
 */
class Count {

  /**
   * Resets the count.
   *
   * @return $this
   */
  public function reset(): static {
    return $this;
  }

  /**
   * Makes a count.
   *
   * @return static
   */
  public static function make(): static {
    return new static();
  }

}
