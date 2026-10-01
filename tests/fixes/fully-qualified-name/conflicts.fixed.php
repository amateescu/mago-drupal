<?php

namespace Drupal\example;

use Drupal\other\Thing;
use Drupal\one\Shared;

final class Settings
{
    /**
     * Writes the short name of a class in a docblock.
     *
     * @param Url $url
     *   The url.
     */
    public function build($url): void
    {
        $thing = new Thing();
        $taken = new \Drupal\example\Sub\Thing();
        $declared = new \Drupal\Core\Site\Settings();
        $documented = \Drupal\Core\Url::fromRoute('x');
        $first = new Shared();
        $second = new \Drupal\two\Shared();
    }
}
