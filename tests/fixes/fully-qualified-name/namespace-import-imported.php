<?php

namespace Drupal\example;

use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Utils;

/**
 * Reads the body through a stream, with the class imported already.
 */
function stream_imported(string $body): string
{
    return Psr7\Utils::streamFor($body)->getContents();
}
