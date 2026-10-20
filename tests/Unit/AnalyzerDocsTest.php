<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use PHPUnit\Framework\TestCase;

use function array_merge;
use function dirname;
use function implode;
use function sort;
use function str_contains;

/**
 * Keeps the analyzer pages in step with the checks that the corpus pins.
 */
final class AnalyzerDocsTest extends TestCase
{
    public function testEveryCheckHasOneEntry(): void
    {
        $documented = [];
        foreach (self::pages()->pages() as $page) {
            foreach ($page['entries'] as $entry) {
                $documented[] = $entry->code;
            }
        }

        sort($documented);

        self::assertSame(self::pages()->pinnedCodes(), $documented);
    }

    public function testEntriesHaveTheirFacts(): void
    {
        $problems = [];
        foreach (self::pages()->pages() as $page) {
            foreach ($page['entries'] as $entry) {
                $problems = array_merge($problems, $entry->problems(CorePreset::ignoredChecks()));
            }
        }

        self::assertSame([], $problems, implode("\n", $problems));
    }

    public function testEntriesAreSeparated(): void
    {
        $docs = new DocsPages(self::root());
        $missing = [];
        foreach (self::pages()->pages() as $page) {
            $text = $docs->read($page['page']);
            foreach ($page['entries'] as $entry) {
                if (str_contains($text, "\n---\n\n## {$entry->code}\n")) {
                    continue;
                }

                $missing[] = "{$page['page']}: {$entry->code}";
            }
        }

        self::assertSame([], $missing, 'Put a `---` line, with a blank line below it, above these entries.');
    }

    private static function pages(): CheckPages
    {
        return new CheckPages(new DocsPages(self::root()), self::root());
    }

    private static function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
