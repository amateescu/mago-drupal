<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function explode;
use function in_array;
use function str_ends_with;

/**
 * Recognises Drupal test code by its path.
 *
 * Tests live under a `tests` directory: `<module>/tests/src/…` and
 * `core/tests/…`. Diagnostics about runtime wiring are noise there, since
 * tests mock managers and exercise deprecated services on purpose.
 *
 * @internal
 */
final class TestFiles
{
    /**
     * Whether the file sits under a `tests` directory.
     */
    public static function isTest(string $path): bool
    {
        return in_array('tests', explode('/', $path), strict: true);
    }

    /**
     * Whether the file is test code or hook documentation. The lookup checks
     * leave both alone: tests build their own containers and exercise
     * deprecated code on purpose, and `*.api.php` examples use made-up ids.
     */
    public static function isTestOrHookDocumentation(string $path): bool
    {
        return self::isTest($path) || str_ends_with($path, '.api.php');
    }
}
