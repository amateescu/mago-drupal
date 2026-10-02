<?php

namespace Drupal\example;

use GuzzleHttp\Psr7\Utils;

/**
 * Reads the body through a stream.
 */
function stream_body(string $body): string
{
    return Utils::streamFor($body)->getContents();
}
