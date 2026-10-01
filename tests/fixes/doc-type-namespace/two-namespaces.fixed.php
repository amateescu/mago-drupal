<?php

namespace Drupal\one {

    use Drupal\Core\Url;

    /**
     * Imports the class in this block only.
     *
     * @param Url $url
     *   The url.
     */
    function first($url): void
    {
    }
}

namespace Drupal\two {

    /**
     * Has no import of its own.
     *
     * @param Url $url
     *   The url.
     */
    function second($url): void
    {
    }
}
