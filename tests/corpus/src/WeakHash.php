<?php

declare(strict_types=1);

namespace Drupal\Demo;

use function hash;
use function md5;
use function sha1;

function direct_call(string $input): string
{
    // @mago-expect lint:drupal/weak-hash
    return md5($input);
}

function extra_arguments(string $input): string
{
    // @mago-expect lint:drupal/weak-hash
    return sha1($input, true);
}

function weak_algorithm(string $input): string
{
    // @mago-expect lint:drupal/weak-hash
    return hash('sha1', $input);
}

function supported_algorithm(string $input): string
{
    return hash('xxh64', $input);
}
