<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_merge;
use function array_values;
use function dirname;
use function glob;
use function implode;
use function sort;
use function strlen;
use function substr;

/**
 * Keeps the docs in step with the registered rules and the parity map.
 *
 * @mago-expect lint:kan-defect
 */
final class DocsTest extends TestCase
{
    public function testGeneratedPartsAreCurrent(): void
    {
        $pages = self::pages();
        foreach (DocsPages::GENERATED as $page) {
            self::assertSame(
                $pages->render($page),
                $pages->read($page),
                "The generated part of docs/{$page} is out of date. Run `just docs-gen`.",
            );
        }
    }

    public function testEveryPageIsInTheNavigation(): void
    {
        $docs = self::root() . '/docs/';
        $files = [];
        foreach ([...((array) glob($docs . '*.md')), ...((array) glob($docs . '*/*.md'))] as $file) {
            $files[] = substr((string) $file, offset: strlen($docs));
        }

        $missing = array_values(array_diff($files, self::pages()->navigation()));

        self::assertSame([], $missing, 'Add these pages to the nav of zensical.toml.');
    }

    public function testEveryRuleHasOneEntry(): void
    {
        $registered = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $registered[] = $rule->getDefinition()->code;
        }

        $documented = [];
        foreach (self::pages()->rulePages() as $page) {
            foreach ($page['entries'] as $entry) {
                $documented[] = $entry->code;
            }
        }

        sort($registered);
        sort($documented);

        self::assertSame($registered, $documented);
    }

    public function testEntriesMatchTheRules(): void
    {
        $rules = [];
        foreach (DrupalExtension::fromArguments(['--core'])->linterRules as $rule) {
            $rules[$rule->getDefinition()->code] = $rule->getDefinition();
        }

        $default = [];
        foreach (DrupalExtension::create()->linterRules as $rule) {
            $default[$rule->getDefinition()->code] = $rule->getDefinition();
        }

        $pages = self::pages();
        $problems = [];
        foreach ($pages->rulePages() as $page) {
            foreach ($page['entries'] as $entry) {
                $core = $rules[$entry->code] ?? null;
                $rule = $default[$entry->code] ?? null;
                if ($core === null || $rule === null) {
                    continue;
                }

                $problems = array_merge($problems, $entry->problems($rule, $core, $pages->map()->ports($entry->code)));
            }
        }

        self::assertSame([], $problems, implode("\n", $problems));
    }

    private static function pages(): DocsPages
    {
        return new DocsPages(self::root());
    }

    private static function root(): string
    {
        return dirname(__DIR__, levels: 2);
    }
}
