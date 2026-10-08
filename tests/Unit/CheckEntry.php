<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function explode;
use function ltrim;
use function preg_match;
use function preg_replace;
use function rtrim;
use function str_contains;

/**
 * The entry of one analyzer check on an analyzer page: a `## drupal/<code>`
 * or `## phpunit/<code>` heading, a list of facts, and the description.
 */
final class CheckEntry
{
    /**
     * A Level line: `error` or `warning`, and an optional condition for the
     * other level, as in "error, or warning on `#value_callback`".
     */
    private const LEVEL = '/^- \*\*Level:\*\* ((?:error|warning)(?:, or (?:error|warning) .+)?)$/m';

    public function __construct(
        public readonly string $code,
        public readonly string $body,
    ) {}

    /**
     * Returns the text of the Level line, or null without a valid one.
     */
    public function level(): ?string
    {
        $match = [];

        return preg_match(self::LEVEL, $this->facts(), $match) === 1 ? $match[1] : null;
    }

    /**
     * Returns how the facts list breaks the rules for an analyzer entry: a
     * Level line, one Source line, and no Fix line, since checks have no fix.
     *
     * @return list<string>
     */
    public function problems(): array
    {
        $facts = $this->facts();
        $problems = [];
        if ($this->level() === null) {
            $problems[] = "{$this->code}: the entry needs a Level line of error or warning.";
        }

        if (str_contains($facts, '- **Fix:**')) {
            $problems[] = "{$this->code}: an analyzer check has no fix, so the entry needs no Fix line.";
        }

        return [...$problems, ...SourceLine::problems($this->code, $facts)];
    }

    /**
     * Returns the first sentence of the first item of the Reports list,
     * without its closing punctuation, or the empty string.
     */
    public function firstReport(): string
    {
        $match = [];
        if (preg_match('/^\*\*Reports:\*\*\n\n- (.+(?:\n  .+)*)/m', $this->body, $match) !== 1) {
            return '';
        }

        $item = (string) preg_replace('/\s*\n\s*/', replacement: ' ', subject: $match[1]);
        $sentence = preg_match('/^(.+?[^.\s])\.\s+(?=[A-Z])/', $item, $match) === 1 ? $match[1] : $item;

        return rtrim($sentence, characters: ';.');
    }

    /**
     * Returns the facts list, which ends at the first blank line.
     */
    private function facts(): string
    {
        return explode(separator: "\n\n", string: ltrim($this->body, characters: "\n"))[0];
    }
}
