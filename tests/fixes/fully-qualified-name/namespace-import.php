<?php

namespace Drupal\example;

use GuzzleHttp\Psr7;

/**
 * Reads the body through a stream.
 */
function stream_body(string $body): string
{
    return Psr7\Utils::streamFor($body)->getContents();
}
