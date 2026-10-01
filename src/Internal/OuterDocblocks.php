<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function explode;
use function in_array;
use function max;
use function strtolower;
use function trim;

/**
 * Finds the docblocks that Drupal.Commenting.DocComment checks: the ones
 * outside a function or method body, which are declaration comments.
 *
 * A docblock inside a function-like body belongs to InlineComment. A local
 * `/** @var Foo $x *\/` annotation is a common idiom, not a malformed
 * declaration comment. A `Closure` is not skipped. Coder's own sniff does not
 * skip a top-level closure either, because it tests only `T_FUNCTION`. A
 * nested closure is inside a covered body in any case. A PHP 8.4 property
 * hook body is not covered. Drupal 11 runs on PHP 8.3.
 *
 * @internal
 */
final class OuterDocblocks
{
    /**
     * First-line markers of an api.module documentation group.
     *
     * A `@defgroup` or `@addtogroup` block is topic markup, not a
     * declaration's docblock. Its closing block is a bare `@}`. Coder skips
     * all of these on the first content token alone. A group block that
     * also holds `@section` or `@see` markup is thus still exempt. A check
     * that every tag is exempt reports real api.php group blocks.
     */
    private const GROUP_MARKERS = ['@defgroup', '@addtogroup', '@coversdefaultclass', '@}'];

    private function __construct() {}

    /**
     * The docblocks outside function and method bodies, in source order.
     *
     * The rule that calls this must target `Function` and `Method`. Rust
     * then collects every one of them into the file's target-node list, and
     * this reads that list at no cost. A getNodes() call per kind takes about
     * 0.13ms per file per kind on Drupal core, because each call is a full,
     * unindexed re-scan.
     *
     * @return list<Span>
     */
    public static function of(SourceFile $file): array
    {
        // The target list is in source order. The bodies are thus kept as
        // two parallel arrays: each start, and the furthest end seen up to
        // it. A docblock then binary-searches the last body that starts
        // before it. The docblock is inside a body if that furthest end is
        // past the docblock. A scan of every body for every docblock is
        // quadratic on a class with hundreds of methods.
        $starts = [];
        $ends = [];
        $furthest = 0;
        foreach ($file->getTargetNodes() as $node) {
            if ($node->kind !== NodeKind::Function && $node->kind !== NodeKind::Method) {
                continue;
            }

            $furthest = max($furthest, $node->span->end);
            $starts[] = $node->span->start;
            $ends[] = $furthest;
        }

        $docblocks = [];
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment || self::insideABody($trivia->span, $starts, $ends)) {
                continue;
            }

            $docblocks[] = $trivia->span;
        }

        return $docblocks;
    }

    /**
     * Whether a docblock's first content marks it as a documentation group.
     */
    public static function isGroup(SourceFile $file, Span $span): bool
    {
        foreach (Docblocks::lines($file, $span) as $line) {
            $text = trim($line->text);
            if ($text === '') {
                continue;
            }

            $firstWord = strtolower(explode(' ', $text, limit: 2)[0]);

            return in_array($firstWord, self::GROUP_MARKERS, strict: true);
        }

        return false;
    }

    /**
     * @param list<int> $starts Body starts, ascending.
     * @param list<int> $ends The furthest body end up to each start.
     */
    private static function insideABody(Span $span, array $starts, array $ends): bool
    {
        $low = 0;
        $high = count($starts) - 1;
        $index = null;
        while ($low <= $high) {
            $middle = ($low + $high) >> 1;
            if ($starts[$middle] > $span->start) {
                $high = $middle - 1;

                continue;
            }

            $index = $middle;
            $low = $middle + 1;
        }

        return $index !== null && $ends[$index] >= $span->end;
    }
}
