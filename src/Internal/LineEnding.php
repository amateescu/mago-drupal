<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function str_contains;

/**
 * The line ending a file uses, for fixes that add lines to it.
 *
 * @internal
 */
final class LineEnding
{
    private function __construct() {}

    /**
     * `"\r\n"` when the file has one, otherwise `"\n"`.
     */
    public static function of(string $contents): string
    {
        return str_contains($contents, "\r\n") ? "\r\n" : "\n";
    }
}
