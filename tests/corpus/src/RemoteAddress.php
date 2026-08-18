<?php

declare(strict_types=1);

namespace Drupal\corpus;

function reads_the_superglobal(): string
{
    // @mago-expect lint:drupal/remote-address
    return $_SERVER['REMOTE_ADDR'];
}

function seeds_the_superglobal(): void
{
    $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
}

function clears_the_superglobal(): void
{
    unset($_SERVER['REMOTE_ADDR']);
}

function clears_among_others(): void
{
    unset($_SESSION['client_ip'], $_SERVER['REMOTE_ADDR']);
}

function seeds_from_a_list(array $addresses): void
{
    [$_SERVER['REMOTE_ADDR']] = $addresses;
}

/**
 * @param array<string, string> $map
 */
function reads_the_address_as_a_key(array $map): string
{
    // A destructuring key is evaluated, so this is a read.
    // @mago-expect lint:drupal/remote-address
    [$_SERVER['REMOTE_ADDR'] => $value] = $map;

    return $value;
}

function reads_another_key(): string
{
    return $_SERVER['HTTP_HOST'];
}
