<?php

namespace Drupal\example;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Utils;

/**
 * Reads the body through a stream, and opens a file through the import too.
 */
function stream_used(string $body): string
{
    Psr7\try_fopen('php://memory', 'r');

    return Utils::streamFor($body)->getContents();
}
