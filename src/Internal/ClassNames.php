<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function in_array;
use function str_ends_with;
use function strtolower;

/**
 * Name-based tests on resolved class names.
 *
 * Where a check compares names instead of asking the codebase for ancestry,
 * or leans on a naming convention Drupal follows closely enough, these do the
 * comparing.
 *
 * @internal
 */
final class ClassNames
{
    private function __construct() {}

    /**
     * Whether any of the names equals one of the wanted classes, case-insensitively.
     *
     * @param list<string> $names
     * @param list<string> $wanted
     */
    public static function anyIs(array $names, array $wanted): bool
    {
        $lowered = [];
        foreach ($wanted as $class) {
            $lowered[] = strtolower($class);
        }

        foreach ($names as $name) {
            if (in_array(strtolower($name), $lowered, strict: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether any of the names ends with one of the suffixes, case-insensitively.
     *
     * @param list<string> $names
     * @param list<string> $suffixes
     */
    public static function anyEndsWith(array $names, array $suffixes): bool
    {
        foreach ($names as $name) {
            foreach ($suffixes as $suffix) {
                if (str_ends_with(strtolower($name), strtolower($suffix))) {
                    return true;
                }
            }
        }

        return false;
    }
}
