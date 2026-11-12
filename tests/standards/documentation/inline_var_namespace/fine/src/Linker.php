<?php

namespace Drupal\example;

use Drupal\Core\Link;

/**
 * Builds links.
 */
class Linker {

  /**
   * Builds a link.
   */
  public function build(object $value): void {
    /** @var \Drupal\Core\Url $url */
    $url = $value;
    /** @var Link $link */
    $link = Link::fromTextAndUrl('Home', $url);
  }

}
