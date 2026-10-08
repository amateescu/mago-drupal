<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use PHPUnit\Framework\TestCase;

use function dirname;
use function implode;

/**
 * Keeps the Ports lines of the entries in step with the phpstan-drupal map.
 */
final class PHPStanDrupalMapTest extends TestCase
{
    public function testPortsLinesMatchTheMap(): void
    {
        $root = dirname(__DIR__, levels: 2);
        $docs = new DocsPages($root);
        $map = PHPStanDrupalMap::load($root . '/tests/phpstan-drupal-map.json');

        $entries = [];
        foreach ($docs->rulePages() as $page) {
            foreach ($page['entries'] as $entry) {
                $entries[$entry->code] = $entry->body;
            }
        }

        foreach ((new CheckPages($docs, $root))->pages() as $page) {
            foreach ($page['entries'] as $entry) {
                $entries[$entry->code] = $entry->body;
            }
        }

        $problems = [];
        foreach ($entries as $code => $body) {
            $named = PHPStanDrupalMap::named($body);
            $mapped = $map->ports($code);
            if ($named === $mapped) {
                continue;
            }

            $problems[] =
                "{$code}: the Ports line names "
                . ($named === [] ? 'no phpstan-drupal rule' : implode(', ', $named))
                . ', and tests/phpstan-drupal-map.json has '
                . ($mapped === [] ? 'none' : implode(', ', $mapped))
                . '.';
        }

        self::assertSame([], $problems, implode("\n", $problems));
    }
}
