<?php

namespace Drupal\example;

use GuzzleHttp\Psr7;

/**
 * Wraps the body in a stream.
 *
 * @return Psr7\Stream
 *   The stream.
 */
function stream_documented(string $body): object
{
    return Psr7\Utils::streamFor($body);
}
