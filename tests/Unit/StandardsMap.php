<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use function explode;
use function file_get_contents;
use function in_array;
use function json_decode;
use function preg_replace;
use function str_replace;
use function strtolower;
use function substr;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * The map of Drupal's written coding standards in
 * `tests/data/standards-map.json`: the sentences of each page, and what
 * handles each of them.
 *
 * A sentence quotes the page word for word in `quotes`. A ` ... ` in a quote
 * skips text. `scope` narrows a quote to one case. The status is `checked`,
 * `partial`, `differs` when the tools do something else than the text,
 * `allowed` when the text permits something and the tools accept it, `none`
 * when a tool could check it and none does, or `prose` for advice and for
 * what only a person can judge. `where` lists the handlers, in the words of
 * the Coder map, plus `preset` for a setting of `drupal.mago.toml`. `cases`
 * names the files under `tests/standards/` that show the sentence at work.
 *
 * The quotes are checked against the commit of the standards repository
 * named in the map, by `tests/Tools/StandardsQuotes.php`, and the cases are
 * run by `tests/Tools/StandardsCases.php`.
 *
 * @phpstan-type Sentence array{quotes: list<string>, status: string, where?: list<string>, cases?: list<string>, scope?: string, note?: string}
 * @phpstan-type Section array{section: string, sentences: list<Sentence>}
 * @phpstan-type Page array{page: string, sections: list<Section>}
 */
final class StandardsMap
{
    public const STATUSES = ['checked', 'partial', 'differs', 'allowed', 'none', 'prose'];

    private const SITE = 'https://project.pages.drupalcode.org/coding_standards/';

    /**
     * @param list<Page> $pages
     */
    private function __construct(
        public readonly string $repository,
        public readonly string $commit,
        public readonly array $pages,
    ) {}

    public static function load(string $path): self
    {
        /** @var array{repository: string, commit: string, pages: list<Page>} $map */
        $map = json_decode(json: (string) file_get_contents($path), associative: true, flags: JSON_THROW_ON_ERROR);

        return new self($map['repository'], $map['commit'], $map['pages']);
    }

    /**
     * The address of a section on the published standards site.
     */
    public static function url(string $page, string $section): string
    {
        $path = substr($page, offset: 0, length: -3);
        $path = $path === 'index' ? '' : str_replace(search: '/index', replace: '', subject: $path) . '/';

        return self::SITE . $path . '#' . self::anchor($section);
    }

    /**
     * The anchor that the standards site gives a heading.
     */
    public static function anchor(string $heading): string
    {
        $text = strtolower(str_replace(search: ['`', '"'], replace: '', subject: $heading));
        $text = (string) preg_replace('/[^a-z0-9 _-]+/', replacement: '', subject: $text);

        return trim((string) preg_replace('/[ ]+/', replacement: '-', subject: trim($text)), characters: '-');
    }

    /**
     * Returns the quotes of the sentences of a page that name a handler,
     * each split at its ` ... ` gaps.
     *
     * @return list<string>
     */
    public function quotedFor(string $handler, string $file): array
    {
        $quotes = [];
        foreach ($this->pages as $page) {
            foreach ($page['page'] === $file ? $page['sections'] : [] as $section) {
                foreach ($section['sentences'] as $sentence) {
                    if (!in_array($handler, $sentence['where'] ?? [], strict: true)) {
                        continue;
                    }

                    foreach ($sentence['quotes'] as $quote) {
                        $quotes = [...$quotes, ...explode(separator: ' ... ', string: $quote)];
                    }
                }
            }
        }

        return $quotes;
    }
}
