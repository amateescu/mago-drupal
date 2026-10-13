<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function str_contains;

/**
 * Finds the rule entries with no `---` line above their heading.
 */
final class EntrySeparators
{
    /**
     * Returns those entries as "page: code".
     *
     * @return list<string>
     */
    public static function missing(DocsPages $pages): array
    {
        $missing = [];
        foreach ($pages->rulePages() as $page) {
            $text = $pages->read($page['page']);
            foreach ($page['entries'] as $entry) {
                if (str_contains($text, "\n---\n\n## {$entry->code}\n")) {
                    continue;
                }

                $missing[] = "{$page['page']}: {$entry->code}";
            }
        }

        return $missing;
    }
}
