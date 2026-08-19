<?php

declare(strict_types=1);

namespace Drupal\corpus;

// @mago-expect lint:drupal/function-comment
function reads_the_superglobal(): string
{
    // @mago-expect lint:drupal/remote-address
    return $_SERVER['REMOTE_ADDR'];
}

// @mago-expect lint:drupal/function-comment
function seeds_the_superglobal(): void
{
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

// @mago-expect lint:drupal/function-comment
function clears_the_superglobal(): void
{
    unset($_SERVER['REMOTE_ADDR']);
}

// @mago-expect lint:drupal/function-comment
function clears_among_others(): void
{
    unset($_SESSION['client_ip'], $_SERVER['REMOTE_ADDR']);
}

// @mago-expect lint:drupal/function-comment
function seeds_from_a_list(array $addresses): void
{
    [$_SERVER['REMOTE_ADDR']] = $addresses;
}

/**
 * Reads the client address out of an array key.
 *
 * @param array<string, string> $map
 *   The array to read from.
 */
function reads_the_address_as_a_key(array $map): string
{
    // A destructuring key is evaluated, so this is a read.
    // @mago-expect lint:drupal/remote-address
    [$_SERVER['REMOTE_ADDR'] => $value] = $map;

    return $value;
}

// @mago-expect lint:drupal/function-comment
function reads_another_key(): string
{
    return $_SERVER['HTTP_HOST'];
}
