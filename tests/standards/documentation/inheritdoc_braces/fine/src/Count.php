<?php

namespace Drupal\example;

/**
 * Holds a count.
 */
class Count implements \Countable {

  /**
   * {@inheritdoc}
   */
  public function count(): int {
    return 0;
  }

  /**
   * Returns the count again.
   *
   * @inheritdoc
   */
  public function total(): int {
    return 0;
  }

}
