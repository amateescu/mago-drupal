<?php

/**
 * Holds the comment lines whose last word looks like a url or a call.
 */
function inline_punctuation_last_word(): void
{
    // Ends with a word that only starts like a url, httpd
    $a = 1;
    // Ends with a word in brackets, (foo)x
    $b = 2;
    // Ends with a link <a href="https://www.drupal.org">
    $c = 3;
    // Ends with a call on an object $this->helper()
    $d = 4;
}
