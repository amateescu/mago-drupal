<?php

/**
 * Rewrites the PHP classes that hold the ports of `tests/coder-map.json` and
 * `tests/phpstan-drupal-map.json`. `PortClassesTest` fails when one of them
 * is out of date.
 *
 * Usage: php tests/GeneratePorts.php
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function dirname;
use function file_put_contents;
use function fwrite;

use const STDOUT;

require dirname(__DIR__) . '/vendor/autoload.php';

$classes = new PortClasses(dirname(__DIR__));
foreach (PortClasses::GENERATED as $file) {
    $text = $classes->render($file);
    if ($text === $classes->read($file)) {
        continue;
    }

    file_put_contents($classes->path($file), $text);
    fwrite(STDOUT, "Updated {$file}\n");
}
