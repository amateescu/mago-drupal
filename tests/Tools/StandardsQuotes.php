<?php

/**
 * Compares the quotes of the standards map and of the rule docs with a
 * checkout of Drupal's coding standards.
 *
 * `tests/data/standards-map.json` names a commit of the standards
 * repository. Every quote of the map must be on its page at that commit,
 * under the section that the map files it in. The sentence quoted under the
 * **Standard** line of a rule's docs entry must be on the linked page, and
 * the link must name a heading of it.
 *
 * To follow a change of the standards, set the commit in the map to the new
 * one and run `just check-standards-quotes`. The script lists the sentences that no
 * longer match.
 *
 * Usage: php tests/Tools/StandardsQuotes.php [<checkout>]
 *
 * The checkout is a clone of the repository. Without one, the script keeps
 * a clone in `.cache/coding-standards`. Either way it checks out the commit
 * of the map there.
 */

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function count;
use function dirname;
use function escapeshellarg;
use function exec;
use function explode;
use function fwrite;
use function implode;
use function is_dir;
use function preg_match;
use function preg_replace;
use function sprintf;
use function trim;

use const STDERR;
use const STDOUT;

require dirname(__DIR__, levels: 2) . '/vendor/autoload.php';

$root = dirname(__DIR__, levels: 2);
$map = StandardsMap::load($root . '/tests/data/standards-map.json');
$checkout = $argv[1] ?? $root . '/.cache/coding-standards';
$git = sprintf('git -C %s ', escapeshellarg($checkout));

$output = [];
$status = 0;
if (!is_dir($checkout)) {
    exec(
        sprintf('git clone --quiet %s %s 2>&1', escapeshellarg($map->repository), escapeshellarg($checkout)),
        $output,
        $status,
    );
}

// The commit is fetched only when the clone doesn't have it yet.
if ($status === 0) {
    $commit = escapeshellarg($map->commit);
    exec("{$git}cat-file -e {$commit}^{commit} 2>/dev/null || {$git}fetch --quiet 2>&1", $output, $status);
}

if ($status === 0) {
    $commit = escapeshellarg($map->commit);
    exec("{$git}checkout --quiet --detach {$commit} 2>&1", $output, $status);
}

if ($status !== 0) {
    fwrite(STDERR, sprintf(
        "%s isn't a clone of %s with the commit %s.\n%s\n",
        $checkout,
        $map->repository,
        $map->commit,
        implode("\n", $output),
    ));
    exit(2);
}

$standards = new StandardsPages($checkout);
$problems = [];
$count = 0;
foreach ($map->pages as $page) {
    foreach ($page['sections'] as $section) {
        foreach ($section['sentences'] as $sentence) {
            foreach ($sentence['quotes'] as $index => $quote) {
                $count++;
                $found = $standards->sectionOf($page['page'], $quote);
                if ($found === null) {
                    $problems[] = "{$page['page']}: the page doesn't have \"{$quote}\".";

                    continue;
                }

                if ($index === 0 && $found !== $section['section']) {
                    $problems[] = "{$page['page']}: \"{$quote}\" is under \"{$found}\", not \"{$section['section']}\".";
                }
            }
        }
    }
}

foreach ((new DocsPages($root))->rulePages() as $docsPage) {
    foreach ($docsPage['entries'] as $entry) {
        $link = [];
        if (preg_match('/^- \*\*Standard:\*\* \[[^\]]+\]\(([^)]+)\)/m', $entry->body, $link) !== 1) {
            continue;
        }

        $target = StandardsPages::fromUrl($link[1]);
        if ($target === null) {
            continue;
        }

        [$file, $anchor] = $target;
        if (!$standards->hasAnchor($file, $anchor)) {
            $problems[] = "{$entry->code}: {$file} doesn't have a heading for #{$anchor}.";
        }

        // The quote is the first block of `> ` lines. A `>` line with no
        // text parts two quotes.
        $block = [];
        if (preg_match('/^(?:>.*\n)+/m', $entry->body, $block) !== 1) {
            continue;
        }

        $lines = (string) preg_replace('/^> ?/m', replacement: '', subject: $block[0]);
        foreach (explode(separator: "\n\n", string: trim($lines)) as $quote) {
            $count++;
            $quote = trim((string) preg_replace('/\s+/', replacement: ' ', subject: $quote));
            if ($standards->sectionOf($file, $quote) !== null) {
                continue;
            }

            $problems[] = "{$entry->code}: {$file} doesn't have \"{$quote}\".";
        }
    }
}

if ($problems !== []) {
    $problems[] = sprintf('%d of %d quotes differ from the standards.', count($problems), $count);
    fwrite(STDERR, implode("\n", $problems) . "\n");
    exit(1);
}

fwrite(STDOUT, "{$count} quotes match the standards.\n");
