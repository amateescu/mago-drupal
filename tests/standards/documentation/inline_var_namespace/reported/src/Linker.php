<?php

namespace Drupal\example;

use Drupal\Core\Url;

/**
 * Builds links.
 */
class Linker {

  /**
   * Builds a link.
   */
  public function build(object $value): void {
    /** @var Url $url */
    $url = $value;
  }

}
