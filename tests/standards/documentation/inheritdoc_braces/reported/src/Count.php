<?php

namespace Drupal\example;

/**
 * Holds a count.
 */
class Count implements \Countable {

  /**
   * @inheritdoc
   */
  public function count(): int {
    return 0;
  }

}
