<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function count;
use function in_array;
use function is_array;
use function substr;

use const T_BREAK;
use const T_CASE;
use const T_CONTINUE;
use const T_DEFAULT;
use const T_ELSE;
use const T_ELSEIF;
use const T_ENDDECLARE;
use const T_ENDFOR;
use const T_ENDFOREACH;
use const T_ENDIF;
use const T_ENDSWITCH;
use const T_ENDWHILE;
use const T_EXIT;
use const T_RETURN;
use const T_THROW;

/**
 * Finds the unreachable statements a PHPStan comment covers.
 *
 * PHPStan reports the first unreachable statement of a block, so the comment
 * that ignores it sits there. Mago reports every statement after it, and the
 * whole run goes with the comment, up to the end of the block. See
 * `PhpTokens::staysInBlock()` for where a block ends.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class UnreachableIgnores
{
    private const IDENTIFIER = 'deadCode.unreachable';

    /**
     * The keywords of a statement that ends the path, so the statements
     * after it in the block are dead.
     */
    private const EXITS = [T_RETURN, T_THROW, T_EXIT, T_CONTINUE, T_BREAK];

    /**
     * The tokens that end a block or start another branch of it, so they
     * don't start a statement of the block.
     */
    private const BRANCH_ENDS = [
        T_ENDIF,
        T_ENDFOREACH,
        T_ENDFOR,
        T_ENDWHILE,
        T_ENDSWITCH,
        T_ENDDECLARE,
        T_ELSE,
        T_ELSEIF,
        T_CASE,
        T_DEFAULT,
    ];

    private function __construct() {}

    /**
     * Whether a comment ignoring unreachable code covers an earlier line in
     * the same block as the offset.
     */
    public static function cover(PHPStanIgnores $ignores, string $contents, int $offset): bool
    {
        foreach ($ignores->lines() as [$start, $end, $identifiers]) {
            // A line form ignores everything on its line, reachable or not,
            // so only the identifier says where a dead run starts.
            $unreachable = is_array($identifiers) && in_array(self::IDENTIFIER, $identifiers, strict: true);
            if (!$unreachable || $end >= $offset) {
                continue;
            }

            $from = self::runStart($contents, $start, $end);
            if (PhpTokens::staysInBlock(substr($contents, $from, $offset - $from))) {
                return true;
            }
        }

        return false;
    }

    /**
     * The offset where the dead run of a covered line starts.
     *
     * That is the first statement after a `return`, `throw`, `exit`,
     * `continue` or `break` statement on the line, in the same block. So in
     * `if ($flag): return; echo 'a';`, the run starts at the `echo`, inside
     * the `if`. A line without one is dead from its start. A block that the
     * line opens, as in `foreach (...):`, is then part of the run.
     */
    private static function runStart(string $contents, int $start, int $end): int
    {
        $tokens = PhpTokens::of($contents);
        $count = count($tokens);
        for ($index = PhpTokens::at($tokens, $start) ?? $count; $index < $count; $index++) {
            if ($tokens[$index]->pos >= $end) {
                break;
            }

            if (!$tokens[$index]->is(self::EXITS) || !self::startsStatement($tokens, $index)) {
                continue;
            }

            $next = self::nextStatement($tokens, $index);
            if ($next !== null && $tokens[$next]->pos < $end) {
                return $tokens[$next]->pos;
            }
        }

        return $start;
    }

    /**
     * Whether the token at `$index` is the first token of a statement.
     *
     * @param list<PhpToken> $tokens
     */
    private static function startsStatement(array $tokens, int $index): bool
    {
        $previous = PhpTokens::previous($tokens, $index - 1);

        return $previous === null || in_array($tokens[$previous]->text, [';', '{', '}', ':'], strict: true);
    }

    /**
     * The index of the statement after the one that starts at `$index`, or
     * null when that statement is the last one of its block or branch.
     *
     * @param list<PhpToken> $tokens
     */
    private static function nextStatement(array $tokens, int $index): ?int
    {
        $count = count($tokens);
        $depth = 0;
        for ($i = $index; $i < $count; $i++) {
            $depth += match ($tokens[$i]->text) {
                '(', '[', '{', '${' => 1,
                ')', ']', '}' => -1,
                default => 0,
            };
            if ($depth < 0) {
                return null;
            }

            if ($depth === 0 && $tokens[$i]->text === ';') {
                $next = PhpTokens::next($tokens, $i + 1);

                return $next === null || $tokens[$next]->text === '}' || $tokens[$next]->is(self::BRANCH_ENDS)
                    ? null
                    : $next;
            }
        }

        return null;
    }
}
