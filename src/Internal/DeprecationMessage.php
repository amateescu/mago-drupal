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
 * Drupal writes the same sentence in `@trigger_error()` calls and in
 * `@deprecated` docblocks, so both rules share this class.
 *
 * @see https://www.drupal.org/node/2856820
 *
 * @internal
 */
final class DeprecationMessage
{
    /**
     * Accepts drupal:n.n.n, project:n.x-n.n and project:n.n.n. An optional
     * release label such as `-beta1` may follow.
     */
    private const VERSION = '/^[a-z\d_]+:(\d{1,2}\.\d{1,2}\.\d{1,2}|\d{1,2}\.x\-\d{1,2}\.\d{1,2})(-[a-z]{1,5}\d{1,2})?$/';

    private const LINK = '#^https?://www\.drupal\.org/(node|project/\w+/issues)/(\d+)(\.*)$#';

    private function __construct() {}

    /**
     * Returns every way in which $text differs from the grammar.
     *
     * @return list<string>
     */
    public static function problems(string $text, DeprecationStandard $standard): array
    {
        $matches = [];
        preg_match($standard->layout(), $text, $matches);

        // The layout patterns capture the thing, the deprecation version, the
        // middle text, the removal version, the extra info and the
        // change-record link.
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
     * Returns how $version differs from the machine-name format, if it does.
     *
     * The `@deprecated` tag grammar shares this method. That grammar writes
     * the same versions in a sentence of a different shape.
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
     * Returns how $link differs from the change-record format, if it does.
     *
     * The `@see` tag that must follow a `@deprecated` tag shares this
     * method. That tag writes the same link outside the message sentence.
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

        // A trailing period is a frequent typo, and the url is correct in all
        // other ways, so it gets its own message.
        if (($matches[3] ?? '') !== '') {
            return "Do not end the change-record url '{$link}' with a period.";
        }

        return null;
    }
}
