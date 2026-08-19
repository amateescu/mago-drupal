<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function hash;
use function md5;
use function sha1;

// @mago-expect lint:drupal/function-comment
function direct_call(string $input): string
{
    // @mago-expect lint:drupal/weak-hash
    return md5($input);
}

// @mago-expect lint:drupal/function-comment
function extra_arguments(string $input): string
{
    // @mago-expect lint:drupal/weak-hash
    return sha1($input, true);
}

// @mago-expect lint:drupal/function-comment
function weak_algorithm(string $input): string
{
    // @mago-expect lint:drupal/weak-hash
    return hash('sha1', $input);
}

// @mago-expect lint:drupal/function-comment
function weak_algorithm_by_name(string $input): string
{
    // @mago-expect lint:drupal/weak-hash
    return hash(algo: 'md5', data: $input);
}

// @mago-expect lint:drupal/function-comment
function supported_algorithm(string $input): string
{
    return hash('xxh64', $input);
}
