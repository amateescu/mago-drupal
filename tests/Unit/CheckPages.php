<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function array_keys;
use function array_slice;
use function explode;
use function file_get_contents;
use function implode;
use function preg_match;
use function preg_match_all;
use function preg_split;
use function sort;
use function str_replace;
use function str_starts_with;
use function substr;
use function ucfirst;

/**
 * Reads the analyzer pages under `docs/analyzer/`, and renders the table of
 * checks on the analyzer index from their entries.
 *
 * @mago-expect lint:kan-defect
 */
final class CheckPages
{
    public const INDEX = 'analyzer/index.md';

    public function __construct(
        private readonly DocsPages $pages,
        private readonly string $root,
    ) {}

    /**
     * Returns the analyzer pages in the order of the navigation, with their
     * title, their first paragraph and their check entries.
     *
     * @return list<array{page: string, title: string, intro: string, entries: list<CheckEntry>}>
     */
    public function pages(): array
    {
        $pages = [];
        foreach ($this->pages->navigation() as $page) {
            if (!str_starts_with($page, 'analyzer/') || $page === self::INDEX) {
                continue;
            }

            $text = $this->pages->read($page);
            $blocks = explode(separator: "\n\n", string: $text);
            $entries = [];
            foreach (array_slice((array) preg_split('/^## /m', $text), offset: 1) as $section) {
                $lines = explode(separator: "\n", string: (string) $section, limit: 2);
                if (preg_match('/^(?:drupal|phpunit)\/[a-z0-9-]+$/', $lines[0]) === 1) {
                    $entries[] = new CheckEntry($lines[0], $lines[1] ?? '');
                }
            }

            $pages[] = [
                'page' => $page,
                'title' => substr($blocks[0], offset: 2),
                'intro' => $blocks[1] ?? '',
                'entries' => $entries,
            ];
        }

        return $pages;
    }

    /**
     * Returns the page of each check entry, keyed by the check's code.
     *
     * @return array<string, string>
     */
    public function entryPages(): array
    {
        $pages = [];
        foreach ($this->pages() as $page) {
            foreach ($page['entries'] as $entry) {
                $pages[$entry->code] = $page['page'];
            }
        }

        return $pages;
    }

    /**
     * Returns the analyzer codes that the corpus pins, sorted. Every check
     * has fixtures there, so these are the codes the docs must cover.
     *
     * @return list<string>
     */
    public function pinnedCodes(): array
    {
        $codes = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root . '/tests/corpus/src', RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            $matches = [];
            preg_match_all(
                '/analysis:((?:drupal|phpunit)\/[a-z0-9-]+)/',
                (string) file_get_contents((string) $file),
                $matches,
            );
            foreach ($matches[1] as $code) {
                $codes[$code] = true;
            }
        }

        $codes = array_keys($codes);
        sort($codes);

        return $codes;
    }

    /**
     * Renders a section for each analyzer page, with a table of its checks.
     */
    public function index(): string
    {
        $sections = [];
        foreach ($this->pages() as $page) {
            $file = substr($page['page'], offset: 9);
            $rows = '';
            foreach ($page['entries'] as $entry) {
                $anchor = str_replace(search: '/', replace: '', subject: $entry->code);
                $report = str_replace(search: '|', replace: '\|', subject: ucfirst($entry->firstReport()));
                $rows .= "| [`{$entry->code}`]({$file}#{$anchor}) | {$entry->level()} | {$report}. |\n";
            }

            if ($rows === '') {
                continue;
            }

            $sections[] = "## {$page['title']}\n\n{$page['intro']}\n\n| Check | Level | What it reports |\n| --- | --- | --- |\n{$rows}";
        }

        return implode("\n", $sections);
    }
}
