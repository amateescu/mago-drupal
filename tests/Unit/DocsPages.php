<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use RuntimeException;

use function array_values;
use function explode;
use function file_get_contents;
use function implode;
use function preg_match_all;
use function preg_replace_callback;
use function preg_split;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Reads the docs pages, and renders the parts of them that come from the
 * registered rules and the parity map.
 *
 * A generated part sits between `<!-- docs-gen:<name> -->` and
 * `<!-- /docs-gen -->`. `just docs-gen` rewrites it, and `DocsTest` fails
 * when it is out of date. The rest of a page is written by hand.
 *
 * @mago-expect lint:kan-defect
 */
final class DocsPages
{
    /**
     * The pages that hold a generated part, relative to `docs/`.
     */
    public const GENERATED = [
        'rules/index.md',
        'coder/drupal.md',
        'coder/other-standards.md',
        'coder/drupal-practice.md',
    ];

    private const REGION = '/(<!-- docs-gen:([a-z0-9-]+) -->\n).*?(<!-- \/docs-gen -->)/s';

    private ?CoderMap $map = null;

    public function __construct(
        private readonly string $root,
    ) {}

    public function path(string $page): string
    {
        return $this->root . '/docs/' . $page;
    }

    public function read(string $page): string
    {
        return (string) file_get_contents($this->path($page));
    }

    public function map(): CoderMap
    {
        return $this->map ??= CoderMap::load($this->root . '/tests/coder-map.json');
    }

    /**
     * Returns the page with each generated part rendered again.
     */
    public function render(string $page): string
    {
        return (string) preg_replace_callback(
            self::REGION,
            fn(array $match): string => $match[1] . "\n" . $this->region($match[2]) . "\n" . $match[3],
            $this->read($page),
        );
    }

    /**
     * Returns the pages listed in the navigation of `zensical.toml`, in order.
     *
     * @return list<string>
     */
    public function navigation(): array
    {
        $matches = [];
        preg_match_all('/"([a-z0-9\/-]+\.md)"/', (string) file_get_contents($this->root . '/zensical.toml'), $matches);

        return array_values($matches[1]);
    }

    /**
     * Returns the rule pages in the order of the navigation, with their title,
     * their first paragraph and their rule entries.
     *
     * @return list<array{page: string, title: string, intro: string, entries: list<RuleEntry>}>
     */
    public function rulePages(): array
    {
        $pages = [];
        foreach ($this->navigation() as $page) {
            if (!str_starts_with($page, 'rules/') || $page === 'rules/index.md') {
                continue;
            }

            $text = $this->read($page);
            $blocks = explode(separator: "\n\n", string: $text);
            $entries = [];
            foreach ((array) preg_split('/^## /m', $text) as $index => $section) {
                $lines = explode(separator: "\n", string: (string) $section, limit: 2);
                if ($index === 0 || !str_starts_with($lines[0], 'drupal/')) {
                    continue;
                }

                $entries[] = new RuleEntry($lines[0], $lines[1] ?? '');
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

    private function region(string $name): string
    {
        $pages = [];
        foreach ($this->rulePages() as $page) {
            foreach ($page['entries'] as $entry) {
                $pages[$entry->code] = $page['page'];
            }
        }

        $tables = new CoderTables($this->map(), $pages);

        return match ($name) {
            'rules' => $this->rules(),
            'coder-drupal' => $tables->drupalStandard(),
            'coder-other' => $tables->otherStandards(),
            'coder-practice' => $tables->drupalPractice(),
            'coder-drupal7' => $tables->drupal7(),
            default => throw new RuntimeException("No generated part is named {$name}."),
        };
    }

    /**
     * Renders a section for each rule page, with a table of its rules.
     */
    private function rules(): string
    {
        $definitions = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $definitions[$rule->getDefinition()->code] = $rule->getDefinition();
        }

        $sections = [];
        foreach ($this->rulePages() as $page) {
            $file = substr($page['page'], offset: 6);
            $rows = '';
            foreach ($page['entries'] as $entry) {
                $definition = $definitions[$entry->code] ?? null;
                if ($definition === null) {
                    continue;
                }

                $anchor = str_replace(search: '/', replace: '', subject: $definition->code);
                $level = strtolower($definition->defaultLevel->name);
                $description = str_replace(search: '|', replace: '\|', subject: $definition->description);
                $rows .= "| [`{$definition->code}`]({$file}#{$anchor}) | {$level} | {$description} |\n";
            }

            $sections[] = "## {$page['title']}\n\n{$page['intro']}\n\n| Rule | Level | What it reports |\n| --- | --- | --- |\n{$rows}";
        }

        return implode("\n", $sections);
    }
}
