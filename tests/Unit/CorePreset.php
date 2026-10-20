<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function array_values;
use function dirname;
use function file_get_contents;
use function preg_match;
use function preg_match_all;

/**
 * Reads the extension's codes out of a config file that projects extend.
 *
 * The files keep each `[linter.rules]` entry on one line, so a pattern finds
 * them.
 */
final class CorePreset
{
    private function __construct() {}

    /**
     * Returns the rule codes that drupal-core.mago.toml turns off.
     *
     * @return list<string>
     */
    public static function rulesTurnedOff(): array
    {
        $matches = [];
        preg_match_all('/^"(drupal\/[a-z0-9-]+)" = \{ enabled = false \}$/m', self::contents(), $matches);

        return array_values($matches[1]);
    }

    /**
     * Returns the extension's rule codes under `[linter.rules]` in $file.
     *
     * @return list<string>
     */
    public static function ruleCodes(string $file = 'drupal-core.mago.toml'): array
    {
        $matches = [];
        preg_match_all('/^"(drupal\/[a-z0-9-]+)" = /m', self::contents($file), $matches);

        return array_values($matches[1]);
    }

    /**
     * Returns the codes in the `[analyzer] ignore` list of drupal-core.mago.toml.
     *
     * @return list<string>
     */
    public static function ignoredChecks(): array
    {
        $list = [];
        if (preg_match('/^ignore = \[\n(.*?)^\]/ms', self::contents(), $list) !== 1) {
            return [];
        }

        $matches = [];
        preg_match_all('/^ {4}"([a-z0-9\/-]+)",$/m', $list[1], $matches);

        return array_values($matches[1]);
    }

    private static function contents(string $file = 'drupal-core.mago.toml'): string
    {
        return (string) file_get_contents(dirname(__DIR__, levels: 2) . '/' . $file);
    }
}
