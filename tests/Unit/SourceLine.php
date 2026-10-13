<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function array_values;
use function count;
use function preg_match_all;
use function str_contains;
use function str_starts_with;
use function trim;

/**
 * The Standard or Source line of a rule entry. A Standard line links the
 * sections of Drupal's coding standards that the rule checks. A Source line
 * names where a check that the written standard does not cover comes from.
 */
final class SourceLine
{
    private const SITE = 'https://project.pages.drupalcode.org/coding_standards/';

    /**
     * Returns how the facts break the rules for these lines: exactly one of
     * them, a Standard line that links only sections of the standards site,
     * and a Source line with text.
     *
     * @return list<string>
     */
    public static function problems(string $code, string $facts): array
    {
        $lines = [];
        preg_match_all('/^- \*\*(Standard|Source):\*\*(.*(?:\n  \S.*)*)/m', $facts, $lines);
        if (count($lines[0]) !== 1) {
            return ["{$code}: the entry needs one Standard or Source line."];
        }

        if ($lines[1][0] === 'Source') {
            return trim($lines[2][0]) === '' ? ["{$code}: the Source line is empty."] : [];
        }

        $problems = [];
        foreach (self::links($lines[2][0]) as $link) {
            if (str_starts_with($link, self::SITE) && str_contains($link, '#')) {
                continue;
            }

            $problems[] = "{$code}: the Standard line links {$link}, not a section of " . self::SITE;
        }

        return $problems;
    }

    /**
     * Returns the links of a Standard line. A line with no link gives
     * "nothing", so it is reported.
     *
     * @return list<string>
     */
    private static function links(string $line): array
    {
        $links = [];
        preg_match_all('/\]\(([^)\s]+)\)/', $line, $links);

        return $links[1] === [] ? ['nothing'] : array_values($links[1]);
    }
}
