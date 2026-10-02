<?php

namespace Drupal\example;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Utils;

/**
 * Wraps the body in a stream.
 *
 * @return Psr7\Stream
 *   The stream.
 */
function stream_documented(string $body): object
{
    return Utils::streamFor($body);
}
