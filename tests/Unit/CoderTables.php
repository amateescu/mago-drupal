<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function array_filter;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function explode;
use function implode;
use function in_array;
use function str_replace;
use function str_starts_with;

/**
 * Renders the tables of the Coming from Coder pages from the parity map.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 *
 * @phpstan-import-type Sniff from CoderMap
 * @phpstan-import-type Code from CoderMap
 */
final class CoderTables
{
    /**
     * The standards that Coder's `Drupal` ruleset takes sniffs from, by the
     * first part of a sniff name, with their headings.
     */
    private const OTHER_STANDARDS = [
        'Generic' => 'Generic',
        'PEAR' => 'PEAR',
        'PSR2' => 'PSR2',
        'Squiz' => 'Squiz',
        'Zend' => 'Zend',
        'SlevomatCodingStandard' => 'Slevomat Coding Standard',
    ];

    /**
     * @param array<string, string> $pages The page of each rule, relative to
     *   `docs/`.
     */
    public function __construct(
        private readonly CoderMap $map,
        private readonly array $pages,
    ) {}

    /**
     * Renders Coder's own sniffs, with a section for each category.
     */
    public function drupalStandard(): string
    {
        $categories = [];
        foreach ($this->current() as $sniff) {
            [$standard, $category] = explode(separator: '.', string: $sniff['sniff']);
            if ($sniff['standard'] !== 'Drupal' || $standard !== 'Drupal') {
                continue;
            }

            $categories[$category][] = $sniff;
        }

        $sections = [];
        foreach ($categories as $category => $members) {
            $sections[] = "## {$category}\n\n" . $this->table($members);
        }

        return implode("\n", $sections);
    }

    /**
     * Renders the sniffs that the `Drupal` ruleset takes from other standards.
     */
    public function otherStandards(): string
    {
        $sections = [];
        foreach (self::OTHER_STANDARDS as $prefix => $title) {
            $members = array_filter(
                $this->current(),
                static fn(array $sniff): bool => $sniff['standard'] === 'Drupal'
                && str_starts_with($sniff['sniff'], $prefix . '.'),
            );
            if ($members === []) {
                continue;
            }

            $sections[] = "## {$title}\n\n" . $this->table($members);
        }

        return implode("\n", $sections);
    }

    /**
     * Renders the sniffs of the `DrupalPractice` standard.
     */
    public function drupalPractice(): string
    {
        return $this->table(array_filter(
            $this->current(),
            static fn(array $sniff): bool => $sniff['standard'] === 'DrupalPractice',
        ));
    }

    /**
     * Renders the checks for the Drupal 7 API.
     */
    public function drupal7(): string
    {
        $rows = "| Sniff | Handled by |\n| --- | --- |\n";
        foreach ($this->map->sniffs as $sniff) {
            if (!($sniff['drupal7'] ?? false)) {
                continue;
            }

            $ported = array_filter($sniff['where'], static fn(string $handler): bool => str_starts_with(
                $handler,
                'ext:',
            ));
            $by = $ported === [] ? 'Not ported' : $this->handledBy($sniff['where'], statuses: ['report']);
            $rows .= "| `{$sniff['sniff']}` | {$by} |\n";
        }

        return $rows;
    }

    /**
     * Returns the sniffs that run on current Drupal code.
     *
     * @return array<int, Sniff>
     */
    private function current(): array
    {
        return array_filter($this->map->sniffs, static fn(array $sniff): bool => !($sniff['drupal7'] ?? false));
    }

    /**
     * Renders a table with one row for each sniff.
     *
     * @param array<int, Sniff> $sniffs
     */
    private function table(array $sniffs): string
    {
        $rows = "| Sniff | Handled by | Notes |\n| --- | --- | --- |\n";
        foreach ($sniffs as $sniff) {
            $rows .= $this->row($sniff);
        }

        return $rows;
    }

    /**
     * Renders the row of a sniff. When its codes go to different places, the
     * row lists the codes for each.
     *
     * @param Sniff $sniff
     */
    private function row(array $sniff): string
    {
        /** @var array<string, list<Code>> $groups */
        $groups = [];
        foreach ($sniff['codes'] as $code) {
            $groups[implode("\n", CoderMap::where($sniff, $code))][] = $code;
        }

        $lines = [];
        foreach ($groups as $handlers => $codes) {
            $statuses = array_values(array_unique(array_map(
                static fn(array $code): string => $code['status'],
                $codes,
            )));
            $by = $this->handledBy(explode(separator: "\n", string: $handlers), $statuses);
            if (in_array('partial', $statuses, strict: true)) {
                $by .= ', partly';
            }

            if (count($groups) === 1) {
                $lines[] = $by;

                continue;
            }

            $names = implode(', ', array_map(static fn(array $code): string => "`{$code['code']}`", $codes));
            $lines[] = "{$names}: {$by}";
        }

        return (
            "| `{$sniff['sniff']}` | "
            . self::cell(implode('<br>', $lines))
            . ' | '
            . self::cell(self::notes($sniff))
            . " |\n"
        );
    }

    /**
     * Returns the notes of a sniff and of its codes.
     *
     * @param Sniff $sniff
     */
    private static function notes(array $sniff): string
    {
        $notes = [];
        $note = $sniff['note'] ?? '';
        if ($note !== '') {
            $notes[] = $note;
        }

        foreach ($sniff['codes'] as $code) {
            $note = $code['note'] ?? '';
            if ($note === '') {
                continue;
            }

            $notes[] = count($sniff['codes']) === 1 ? $note : "`{$code['code']}`: {$note}";
        }

        return implode(' ', $notes);
    }

    /**
     * Names what handles a check.
     *
     * @param list<string> $handlers
     * @param list<string> $statuses
     */
    private function handledBy(array $handlers, array $statuses): string
    {
        $names = [];
        foreach ($handlers as $handler) {
            $parts = explode(separator: ':', string: $handler, limit: 2);
            $tool = $parts[0];
            $name = $parts[1] ?? '';
            $names[] = match ($tool) {
                'ext' => $this->ruleLink($name),
                'lint' => "Mago `{$name}`" . ($statuses === ['gap'] ? ' (off by default)' : ''),
                'format' => '`mago format`',
                'analyze' => $name === '' ? '`mago analyze`' : "`mago analyze` (`{$name}`)",
                'branch' => 'The analyzer (not released)',
                'phpcs' => 'phpcs',
                default => 'Nothing',
            };
        }

        return implode(', ', array_values(array_unique($names)));
    }

    private function ruleLink(string $code): string
    {
        $page = $this->pages[$code] ?? 'rules/index.md';

        return "[`{$code}`](../{$page}#" . str_replace(search: '/', replace: '', subject: $code) . ')';
    }

    private static function cell(string $text): string
    {
        return str_replace(search: ['|', "\n"], replace: ['\|', ' '], subject: $text);
    }
}
