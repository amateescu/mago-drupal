<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\SourceText;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function array_filter;
use function array_values;
use function preg_match;
use function strspn;
use function substr;
use function trim;

/**
 * Reports a comment right after a cast, or between `yield` and `from`.
 *
 * Ports Generic.Formatting.SpaceAfterCast.CommentFound and
 * Generic.WhiteSpace.LanguageConstructSpacing.IncorrectYieldFromWithComment.
 * A comment of any kind counts, on the same line or a later one. A comment
 * before the cast, after the operand, or after `from` is fine. There is no
 * fix, since a comment cannot move without a decision about what it
 * describes.
 */
final class CommentInExpressionRule implements Rule
{
    private const CAST = '/^\(\s*(?:int|integer|bool|boolean|float|double|string|binary|array|object|void)\s*\)$/i';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/comment-in-expression',
            name: 'Comment in expression',
            description: 'Reports a comment right after a cast, or between `yield` and `from`.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::UnaryPrefixOperator, NodeKind::YieldFrom],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $contents = $file->contents;
        $node = $context->node;

        if ($node->kind === NodeKind::UnaryPrefixOperator) {
            if (preg_match(self::CAST, $file->getText($node)) !== 1) {
                return;
            }

            $offset = $node->span->end + strspn($contents, characters: " \t\r\n", offset: $node->span->end);
            if (SourceText::commentEnd($contents, $offset) !== null) {
                $context->report(Issue::new('Expected 1 space after cast statement; comment found.', $node->span));
            }

            return;
        }

        $words = array_values(array_filter(
            $file->getChildren($node),
            static fn(Node $child): bool => $child->kind === NodeKind::Keyword,
        ));

        $yield = $words[0] ?? null;
        $from = $words[1] ?? null;
        if ($yield === null || $from === null || self::gap($contents, $yield, $from) === '') {
            return;
        }

        $context->report(Issue::new('Remove the comment between `yield` and `from`.', $yield->span));
    }

    /**
     * The text between two keywords, without the whitespace around it.
     */
    private static function gap(string $contents, Node $first, Node $second): string
    {
        return trim(substr($contents, $first->span->end, $second->span->start - $first->span->end));
    }
}
