<?php

namespace Drupal\example;

use Drupal\Core\Url;

/**
 * Builds links.
 */
class Linker {

  /**
   * Builds a link.
   *
   * @param \Drupal\Core\Url $url
   *   The address.
   * @param Url $other
   *   Another address, with a short name whose import the code uses.
   */
  public function build($url, Url $other): void {}

}
