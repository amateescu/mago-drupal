<?php

/**
 * Rewrites the generated parts of the docs pages.
 *
 * The tables of the rules index and of the Coder pages come from the
 * registered rules and `tests/data/coder-map.json`. `DocsTest` fails when
 * one of them is out of date.
 *
 * Usage: php tests/Tools/GenerateDocs.php
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function dirname;
use function file_put_contents;
use function fwrite;

use const STDOUT;

require dirname(__DIR__, levels: 2) . '/vendor/autoload.php';

$pages = new DocsPages(dirname(__DIR__, levels: 2));
foreach (DocsPages::GENERATED as $page) {
    $text = $pages->render($page);
    if ($text === $pages->read($page)) {
        continue;
    }

    file_put_contents($pages->path($page), $text);
    fwrite(STDOUT, "Updated docs/{$page}\n");
}
