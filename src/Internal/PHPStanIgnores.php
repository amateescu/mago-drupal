<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use ParseError;
use PhpToken;

use function preg_match_all;
use function str_contains;

use const PREG_SET_ORDER;

/**
 * The lines a file's `@phpstan-ignore` comments cover, with the error
 * identifiers each line ignores.
 *
 * `@phpstan-ignore-line` covers its own line and `@phpstan-ignore-next-line`
 * the line after the comment, both for every identifier, whatever follows the
 * tag. `@phpstan-ignore` names its identifiers, optionally with a reason in
 * parentheses. It covers its own line when code comes before it there, and
 * otherwise the line of the next code, so other comments in between do not
 * count.
 *
 * @internal
 */
final class PHPStanIgnores
{
    private const TAG = '@phpstan-ignore';

    /**
     * The tag, its suffix and the comma-separated identifiers after it, which
     * stop at a reason in parentheses or the end of the comment.
     */
    private const TAGS = '/@phpstan-ignore(?<kind>-next-line|-line)?(?![\w-])[ \t]*(?<ids>[A-Za-z][\w.]*(?:[ \t]*,[ \t]*[A-Za-z][\w.]*)*)?/';

    /**
     * @param list<array{int, int, list<string>|true}> $lines The first and
     *   last offset of each covered line, with the identifiers it ignores, or
     *   true for all of them.
     */
    private function __construct(
        private readonly array $lines,
    ) {}

    /**
     * The comments of a file, or null when it has none.
     */
    public static function of(string $contents): ?self
    {
        if (!str_contains($contents, self::TAG)) {
            return null;
        }

        try {
            $tokens = PhpToken::tokenize($contents);

            // The host analyzes files Mago's own parser accepts, which is not
            // always what PHP's tokenizer accepts. A file it rejects has no
            // comments rather than taking the worker down.
            // @mago-expect analysis:avoid-catching-error
        } catch (ParseError) {
            return null;
        }

        $lines = [];
        foreach ($tokens as $index => $token) {
            if (!$token->is([T_COMMENT, T_DOC_COMMENT]) || !str_contains($token->text, self::TAG)) {
                continue;
            }

            $matches = [];
            preg_match_all(self::TAGS, $token->text, $matches, PREG_SET_ORDER);
            foreach ($matches as $match) {
                $line = PHPStanIgnoreTag::covered(
                    $contents,
                    $tokens,
                    $index,
                    $match['kind'] ?? '',
                    $match['ids'] ?? '',
                );
                if ($line !== null) {
                    $lines[] = $line;
                }
            }
        }

        return $lines === [] ? null : new self($lines);
    }

    /**
     * What the line holding the offset ignores: identifiers, true for all of
     * them, or null when no comment covers the line.
     *
     * @return list<string>|true|null
     */
    public function at(int $offset): array|bool|null
    {
        $found = null;
        foreach ($this->lines as [$start, $end, $identifiers]) {
            if ($offset < $start || $offset > $end) {
                continue;
            }

            if ($identifiers === true) {
                return true;
            }

            $found = [...($found ?? []), ...$identifiers];
        }

        return $found;
    }
}
