<?php

namespace Drupal\example;

use GuzzleHttp\Psr7;

/**
 * Reads the body through a stream, and opens a file through the import too.
 */
function stream_used(string $body): string
{
    Psr7\try_fopen('php://memory', 'r');

    return Psr7\Utils::streamFor($body)->getContents();
}
