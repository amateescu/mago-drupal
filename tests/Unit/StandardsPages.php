<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function array_key_exists;
use function explode;
use function file_get_contents;
use function is_file;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Reads the pages of a checkout of the coding standards repository, and
 * finds quotes in them.
 *
 * A quote matches the text of a page without its Markdown markup, in any
 * letter case and with any whitespace between the words. A ` ... ` in a
 * quote skips text.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class StandardsPages
{
    private const SITE = 'https://project.pages.drupalcode.org/coding_standards/';

    /**
     * The text of each page, where each line of it starts with the heading
     * above it, and the headings of the page.
     *
     * @var array<string, array{string, list<array{int, string}>, list<string>}|null>
     */
    private array $pages = [];

    public function __construct(
        private readonly string $checkout,
    ) {}

    /**
     * The file of the page behind an address on the standards site, with
     * the anchor of the address, or null for another site.
     *
     * @return array{string, string}|null
     */
    public static function fromUrl(string $url): ?array
    {
        if (!str_starts_with($url, self::SITE)) {
            return null;
        }

        $parts = explode(separator: '#', string: substr($url, strlen(self::SITE)), limit: 2);
        $path = trim($parts[0], characters: '/');

        return [($path === '' ? 'index' : $path) . '.md', $parts[1] ?? ''];
    }

    /**
     * The heading above the place where the quote starts on the page, or
     * null when the page doesn't have the quote.
     */
    public function sectionOf(string $page, string $quote): ?string
    {
        $read = $this->read($page);
        if ($read === null) {
            return null;
        }

        [$text, $owners] = $read;
        $offset = 0;
        $first = null;
        foreach ((array) preg_split('/\s+\.\.\.\s+/', $quote) as $part) {
            $needle = self::plain((string) $part);
            $found = $needle === '' ? false : strpos($text, $needle, $offset);
            if ($found === false) {
                return null;
            }

            $first ??= $found;
            $offset = $found + strlen($needle);
        }

        if ($first === null) {
            return null;
        }

        $section = '';
        foreach ($owners as [$start, $heading]) {
            if ($start > $first) {
                break;
            }

            $section = $heading;
        }

        return $section;
    }

    /**
     * Whether the page has a heading with the anchor.
     */
    public function hasAnchor(string $page, string $anchor): bool
    {
        foreach ($this->read($page)[2] ?? [] as $heading) {
            if (StandardsMap::anchor($heading) === $anchor) {
                return true;
            }
        }

        return false;
    }

    /**
     * The text of a line or a quote without its markup.
     */
    public static function plain(string $text): string
    {
        $text = str_replace(
            search: ["\u{201c}", "\u{201d}", "\u{2019}", '\\'],
            replace: ['"', '"', "'", ''],
            subject: $text,
        );
        $text = (string) preg_replace('#</?(?:em|strong|code|br)\s*/?>#', replacement: '', subject: $text);
        $text = (string) preg_replace('/\[([^\]\[\n]*)\]\([^)\n]*\)/', replacement: '$1', subject: $text);
        $text = (string) preg_replace('/[`*_]/', replacement: '', subject: $text);

        return strtolower(trim((string) preg_replace('/\s+/', replacement: ' ', subject: $text)));
    }

    /**
     * @return array{string, list<array{int, string}>, list<string>}|null
     */
    private function read(string $page): ?array
    {
        if (array_key_exists($page, $this->pages)) {
            return $this->pages[$page];
        }

        $file = $this->checkout . '/docs/' . $page;
        if (!is_file($file)) {
            return $this->pages[$page] = null;
        }

        $text = '';
        $owners = [];
        $headings = [];
        $heading = '';
        $fenced = false;
        foreach (explode(separator: "\n", string: (string) file_get_contents($file)) as $line) {
            // A fence with a language always opens a block: one page has a
            // block that is never closed.
            $trimmed = trim($line);
            if (str_starts_with($trimmed, '```')) {
                $fenced = strlen($trimmed) > 3 || !$fenced;
            }

            $match = [];
            if (!$fenced && preg_match('/^#{1,4}\s+(.*?)\s*$/', $line, $match) === 1) {
                $heading = $match[1];
                $headings[] = $heading;
            }

            $plain = self::plain($line);
            if ($plain === '') {
                continue;
            }

            $owners[] = [strlen($text), $heading];
            $text .= $plain . ' ';
        }

        return $this->pages[$page] = [$text, $owners, $headings];
    }
}
