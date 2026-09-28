<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Closure;

/**
 * Keeps what was last built from a file's contents, one entry per kind.
 *
 * Issue filters and hooks ask for the same file's tokens in a row, and a
 * file's issues arrive together, so one entry per kind is enough to tokenize
 * each file once.
 *
 * @internal
 */
final class LastFile
{
    /**
     * @var array<string, array{string, mixed}>
     */
    private static array $last = [];

    private function __construct() {}

    /**
     * The value built from the contents, built again only for other contents.
     *
     * @template T
     *
     * @param Closure(): T $build
     *
     * @return T
     */
    public static function get(string $kind, string $contents, Closure $build): mixed
    {
        $last = self::$last[$kind] ?? null;
        if ($last !== null && $last[0] === $contents) {
            /** @var T */
            return $last[1];
        }

        $value = $build();
        self::$last[$kind] = [$contents, $value];

        return $value;
    }
}
