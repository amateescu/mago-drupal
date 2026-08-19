<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_filter;
use function array_values;
use function count;
use function preg_match;

/**
 * Checks a deprecation message against Drupal's documented grammar.
 *
 * Drupal writes the same sentence in `@trigger_error()` calls and `@deprecated`
 * docblocks, so both rules share this.
 *
 * @see https://www.drupal.org/node/2856820
 *
 * @internal
 */
final class DeprecationMessage
{
    /**
     * Accepts drupal:n.n.n, project:n.x-n.n and project:n.n.n, with an optional
     * release label such as `-beta1`.
     */
    private const VERSION = '/^[a-z\d_]+:(\d{1,2}\.\d{1,2}\.\d{1,2}|\d{1,2}\.x\-\d{1,2}\.\d{1,2})(-[a-z]{1,5}\d{1,2})?$/';

    private const LINK = '#^https?://www\.drupal\.org/(node|project/\w+/issues)/(\d+)(\.*)$#';

    private function __construct() {}

    /**
     * Returns every way $text departs from the grammar.
     *
     * @return list<string>
     */
    public static function problems(string $text, DeprecationStandard $standard): array
    {
        $matches = [];
        preg_match($standard->layout(), $text, $matches);

        // The layout patterns capture thing, deprecation version, middle text,
        // removal version, extra info and the change-record link.
        if (count($matches) !== 7) {
            return [
                "The deprecation message does not match the {$standard->label()} standard format: "
                    . $standard->format(),
            ];
        }

        $problems = [
            self::versionProblem('deprecation version', $matches[2]),
            self::versionProblem('removal version', $matches[4]),
            self::linkProblem($matches[6]),
        ];

        return array_values(array_filter($problems));
    }

    /**
     * Returns how $version departs from the machine-name format, if it does.
     *
     * Shared with the `@deprecated` tag grammar, which writes the same
     * versions in a differently shaped sentence.
     */
    public static function versionProblem(string $label, string $version): ?string
    {
        if (preg_match(self::VERSION, $version) === 1) {
            return null;
        }

        return (
            "The {$label} '{$version}' does not match the lower-case machine-name standard: "
            . 'drupal:n.n.n or project:n.x-n.n or project:n.n.n, with an optional release label.'
        );
    }

    /**
     * Returns how $link departs from the change-record format, if it does.
     *
     * Shared with the `@see` tag that must follow a `@deprecated` tag, which
     * writes the same link outside the message sentence.
     */
    public static function linkProblem(string $link): ?string
    {
        $matches = [];
        preg_match(self::LINK, $link, $matches);

        if ($matches === []) {
            return (
                "The change-record url '{$link}' does not match the standard: "
                . 'https://www.drupal.org/node/n or https://www.drupal.org/project/name/issues/n'
            );
        }

        // A trailing period is a common typo and the url is otherwise correct,
        // so it gets its own message.
        if (($matches[3] ?? '') !== '') {
            return "The change-record url '{$link}' should not end with a period.";
        }

        return null;
    }
}
