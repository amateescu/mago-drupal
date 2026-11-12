<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use PHPUnit\Framework\TestCase;

use function array_key_exists;
use function dirname;
use function implode;
use function in_array;
use function preg_match;
use function preg_replace;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Keeps the standards map well formed, and in step with the registered
 * rules and the rule entries of the docs.
 *
 * `tests/Tools/StandardsQuotes.php` compares the quotes with the standards.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 *
 * @phpstan-import-type Sentence from StandardsMap
 */
final class StandardsMapTest extends TestCase
{
    /**
     * A sentence has a quote and a known status. It names a handler when it
     * is checked, and says in a note what is left out or what differs.
     */
    public function testSentencesAreWellFormed(): void
    {
        $problems = [];
        foreach (self::sentences() as [$where, $sentence]) {
            $status = $sentence['status'];
            $handlers = $sentence['where'] ?? [];
            $note = $sentence['note'] ?? '';
            if ($sentence['quotes'] === []) {
                $problems[] = "{$where}: no quote.";
            }

            if (!in_array($status, StandardsMap::STATUSES, strict: true)) {
                $problems[] = "{$where}: the status {$status} is unknown.";
            }

            if (in_array($status, ['checked', 'partial'], strict: true) && $handlers === []) {
                $problems[] = "{$where}: a {$status} sentence names a handler.";
            }

            if (in_array($status, ['none', 'prose'], strict: true) && $handlers !== []) {
                $problems[] = "{$where}: a sentence that isn't checked doesn't name a handler.";
            }

            if (in_array($status, ['partial', 'differs'], strict: true) && $note === '') {
                $problems[] = "{$where}: a {$status} sentence says in a note what is left out or what differs.";
            }
        }

        self::assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * A handler is a registered rule, a documented analyzer check, or a
     * name in the words of the Coder map.
     */
    public function testHandlersExist(): void
    {
        $rules = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $rules[] = $rule->getDefinition()->code;
        }

        $root = dirname(__DIR__, levels: 2);
        $checks = (new CheckPages(new DocsPages($root), $root))->entryPages();
        $problems = [];
        foreach (self::sentences() as [$where, $sentence]) {
            foreach ($sentence['where'] ?? [] as $handler) {
                $known = match (true) {
                    str_starts_with($handler, 'ext:') => in_array(substr($handler, offset: 4), $rules, strict: true),
                    str_starts_with($handler, 'check:') => array_key_exists(substr($handler, offset: 6), $checks),
                    default => preg_match('/^(?:format|preset|(?:lint|analyze):[a-z0-9-]+)$/', $handler) === 1,
                };
                if ($known) {
                    continue;
                }

                $problems[] = "{$where}: the handler {$handler} is unknown.";
            }
        }

        self::assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * The text that a rule's entry quotes from the standards holds a
     * sentence of the map that names the rule as a handler.
     *
     * The quote is the first block of `> ` lines of the entry.
     */
    public function testRuleEntriesAreInTheMap(): void
    {
        $root = dirname(__DIR__, levels: 2);
        $map = StandardsMap::load($root . '/tests/data/standards-map.json');
        $problems = [];
        foreach ((new DocsPages($root))->rulePages() as $page) {
            foreach ($page['entries'] as $entry) {
                $link = [];
                $block = [];
                if (
                    preg_match('/^- \*\*Standard:\*\* \[[^\]]+\]\(([^)]+)\)/m', $entry->body, $link) !== 1
                    || preg_match('/^(?:>.*\n)+/m', $entry->body, $block) !== 1
                ) {
                    continue;
                }

                $file = (StandardsPages::fromUrl($link[1]) ?? [''])[0];
                $quoted = $map->quotedFor('ext:' . $entry->code, $file);
                $text = StandardsPages::plain((string) preg_replace('/^> ?/m', replacement: '', subject: $block[0]));
                if (self::overlaps($text, $quoted)) {
                    continue;
                }

                $problems[] = "{$entry->code}: the map doesn't name the rule under a sentence of \"{$text}\" in {$file}.";
            }
        }

        self::assertSame([], $problems, implode("\n", $problems));
    }

    /**
     * Whether the text holds one of the quotes, or one of them holds it.
     *
     * @param list<string> $quotes
     */
    private static function overlaps(string $text, array $quotes): bool
    {
        foreach ($quotes as $quote) {
            $quote = StandardsPages::plain($quote);
            if ($quote !== '' && (str_contains($text, $quote) || str_contains($quote, $text))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{string, Sentence}>
     */
    private static function sentences(): array
    {
        $map = StandardsMap::load(dirname(__DIR__, levels: 2) . '/tests/data/standards-map.json');
        $sentences = [];
        foreach ($map->pages as $page) {
            foreach ($page['sections'] as $section) {
                foreach ($section['sentences'] as $sentence) {
                    $sentences[] = ["{$page['page']}, {$section['section']}, \"{$sentence['quotes'][0]}\"", $sentence];
                }
            }
        }

        return $sentences;
    }
}
