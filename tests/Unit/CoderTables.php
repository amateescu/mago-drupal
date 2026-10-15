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
use function rtrim;
use function str_replace;
use function str_starts_with;
use function strtolower;

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
            $sections[] = "## {$category}\n\n" . $this->table($members, heading: '###');
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

            $sections[] = "## {$title}\n\n" . $this->table($members, heading: '###');
        }

        return implode("\n", $sections);
    }

    /**
     * Renders the sniffs of the `DrupalPractice` standard. The table sits
     * right under the page title, so a sniff's own section has a level 2
     * heading.
     */
    public function drupalPractice(): string
    {
        return $this->table(
            array_filter($this->current(), static fn(array $sniff): bool => $sniff['standard'] === 'DrupalPractice'),
            heading: '##',
        );
    }

    /**
     * Renders the checks for the Drupal 7 API, with their notes. A sniff that
     * a rule or an analyzer check handles names it, and the rest are not
     * ported.
     */
    public function drupal7(): string
    {
        $rows = "| Sniff | Handled by | Notes |\n| --- | --- | --- |\n";
        foreach ($this->map->sniffs as $sniff) {
            if (!($sniff['drupal7'] ?? false)) {
                continue;
            }

            $ported = array_filter(
                $sniff['where'],
                static fn(string $handler): bool => (
                    str_starts_with($handler, 'ext:') || str_starts_with($handler, 'check:')
                ),
            );
            $partial = array_filter($sniff['codes'], static fn(array $code): bool => $code['status'] === 'partial');
            $by = match (true) {
                $ported === [] => 'Not ported',
                $partial === [] => $this->handledBy($sniff['where'], statuses: ['report']),
                default => $this->handledBy($sniff['where'], statuses: ['partial']) . ', partly',
            };
            $rows .= "| `{$sniff['sniff']}` | {$by} | " . self::cell(self::notes($sniff)) . " |\n";
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
     * Renders a table with one row for each sniff. A sniff whose codes go to
     * different places, or that has notes, gets a section below the table,
     * under a heading of the given level, and its name links to it.
     *
     * @param array<int, Sniff> $sniffs
     */
    private function table(array $sniffs, string $heading): string
    {
        $rows = "| Sniff | Handled by |\n| --- | --- |\n";
        $sections = [];
        foreach ($sniffs as $sniff) {
            $groups = self::groups($sniff);
            $name = "`{$sniff['sniff']}`";
            if (count($groups) > 1 || self::notes($sniff) !== '') {
                $name =
                    "[{$name}](#" . strtolower(str_replace(search: '.', replace: '', subject: $sniff['sniff'])) . ')';
                $sections[] = $this->section($sniff, $groups, $heading);
            }

            $rows .= "| {$name} | " . self::cell($this->handledByAll($sniff, $groups)) . " |\n";
        }

        return $sections === [] ? $rows : $rows . "\n" . implode("\n", $sections);
    }

    /**
     * Names everything that handles the codes of a sniff.
     *
     * @param Sniff $sniff
     * @param list<array{handlers: list<string>, codes: list<Code>}> $groups
     */
    private function handledByAll(array $sniff, array $groups): string
    {
        $handlers = [];
        $codes = [];
        foreach ($groups as $group) {
            $handlers = [...$handlers, ...$group['handlers']];
            $codes = [...$codes, ...$group['codes']];
        }

        return $this->groupHandledBy($sniff, ['handlers' => array_values(array_unique($handlers)), 'codes' => $codes]);
    }

    /**
     * Renders the section of a sniff: the sniff's note, a row for each group
     * of codes when they go to different places, then the notes of the codes.
     *
     * @param Sniff $sniff
     * @param list<array{handlers: list<string>, codes: list<Code>}> $groups
     */
    private function section(array $sniff, array $groups, string $heading): string
    {
        $parts = [];
        $note = $sniff['note'] ?? '';
        if ($note !== '') {
            $parts[] = $note;
        }

        if (count($groups) > 1) {
            $rows = "| Codes | Handled by |\n| --- | --- |\n";
            foreach ($groups as $group) {
                $names = implode(', ', array_map(
                    static fn(array $code): string => "`{$code['code']}`",
                    $group['codes'],
                ));
                $rows .= "| {$names} | " . self::cell($this->groupHandledBy($sniff, $group)) . ' |' . "\n";
            }

            $parts[] = rtrim($rows);
        }

        $notes = [];
        foreach ($sniff['codes'] as $code) {
            $codeNote = $code['note'] ?? '';
            if ($codeNote !== '') {
                $notes[] = count($sniff['codes']) === 1 ? $codeNote : "- `{$code['code']}`: {$codeNote}";
            }
        }

        if ($notes !== []) {
            $parts[] = implode("\n", $notes);
        }

        return "{$heading} `{$sniff['sniff']}`\n\n" . implode("\n\n", $parts) . "\n";
    }

    /**
     * Names what handles a group of codes, with "partly" when one of them is
     * handled only in part.
     *
     * @param Sniff $sniff
     * @param array{handlers: list<string>, codes: list<Code>} $group
     */
    private function groupHandledBy(array $sniff, array $group): string
    {
        $statuses = array_values(array_unique(array_map(
            static fn(array $code): string => $code['status'],
            $group['codes'],
        )));
        $by = $this->handledBy($group['handlers'], $statuses, $sniff['gate'] ?? []);

        return in_array('partial', $statuses, strict: true) ? $by . ', partly' : $by;
    }

    /**
     * The codes of a sniff, grouped by what handles them. A partial code goes
     * in a group of its own, so that "partly" names only the codes it applies
     * to.
     *
     * @param Sniff $sniff
     *
     * @return list<array{handlers: list<string>, codes: list<Code>}>
     */
    private static function groups(array $sniff): array
    {
        $groups = [];
        foreach ($sniff['codes'] as $code) {
            $handlers = CoderMap::where($sniff, $code);
            $key = implode("\n", $handlers) . ($code['status'] === 'partial' ? "\npartial" : '');
            $groups[$key] ??= ['handlers' => $handlers, 'codes' => []];
            $groups[$key]['codes'][] = $code;
        }

        return array_values($groups);
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
     * Names what handles a check. $gates maps a handler to what it needs to
     * run, which is shown after its name. A Mago rule that handles only gaps
     * and doesn't have an entry there is off by default.
     *
     * @param list<string> $handlers
     * @param list<string> $statuses
     * @param array<string, string> $gates
     */
    private function handledBy(array $handlers, array $statuses, array $gates = []): string
    {
        $names = [];
        foreach ($handlers as $handler) {
            $parts = explode(separator: ':', string: $handler, limit: 2);
            $tool = $parts[0];
            $name = $parts[1] ?? '';
            $gate = $gates[$handler] ?? null;
            $label = match ($tool) {
                'ext', 'check' => $this->ruleLink($name),
                'lint' => "Mago `{$name}`" . ($gate === null && $statuses === ['gap'] ? ' (off by default)' : ''),
                'format' => '`mago format`',
                'analyze' => $name === '' ? '`mago analyze`' : "`mago analyze` (`{$name}`)",
                'branch' => 'The analyzer (not released)',
                'phpcs' => 'phpcs',
                default => 'Nothing',
            };
            $names[] = $gate === null ? $label : "{$label} ({$gate})";
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
