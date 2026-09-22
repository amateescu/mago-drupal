<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockLine;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function explode;
use function str_contains;
use function strtolower;

/**
 * Checks that a class, interface, trait or enum has a docblock.
 *
 * Ports Drupal.Commenting.ClassComment. A file that holds one class often
 * writes its docblock as the file comment instead. That is why a docblock
 * tagged `@file` does not count as the class docblock. The
 * `drupal/file-comment` rule already covers the file comment.
 */
final class ClassCommentRule implements Rule
{
    /**
     * Keyed by the `NodeKind` case name. PHP 8.1 rejects `->value` on an
     * enum case inside a class constant.
     */
    private const KEYWORDS = [
        'Class_' => 'class',
        'Interface' => 'interface',
        'Trait' => 'trait',
        'Enum' => 'enum',
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/class-comment',
            name: 'Class comment',
            description: 'Checks that a class, interface, trait or enum has a docblock.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum],
        );
    }

    public function lint(LintContext $context): void
    {
        $keyword = self::KEYWORDS[$context->node->kind->name];
        $closest = Docblocks::closest($context->file, $context->node);

        if ($closest === null) {
            $context->report(Issue::new("The {$keyword} has no docblock.", $context->node->span));

            return;
        }

        if ($closest->kind !== TriviaKind::DocBlockComment) {
            $context->report(Issue::new("The {$keyword} docblock must start with \"/**\".", $context->node->span));

            return;
        }

        $tags = Docblocks::tags($context->file, $closest->span);
        foreach ($tags as $tag) {
            if ($tag->name !== 'file') {
                continue;
            }

            $context->report(Issue::new("The {$keyword} has no docblock.", $context->node->span));

            return;
        }

        $summary = Docblocks::leadingLines($context->file, $closest->span);
        $this->checkShort($context, $summary, $keyword);
    }

    /**
     * @param list<DocblockLine> $summary
     */
    private function checkShort(LintContext $context, array $summary, string $keyword): void
    {
        $words = [];
        foreach ($summary as $line) {
            foreach (explode(' ', $line->text) as $word) {
                if ($word === '') {
                    continue;
                }

                $words[] = $word;
            }
        }

        if (count($words) === 0 || count($words) > 2) {
            return;
        }

        $name = Nodes::declaredName($context->file, $context->node);
        if ($name === null) {
            return;
        }

        foreach ($words as $word) {
            if (!str_contains(strtolower($word), strtolower($name))) {
                continue;
            }

            $context->report(Issue::new(
                "The {$keyword} docblock only repeats the {$keyword} name. Describe what the {$keyword} does.",
                $context->node->span,
            ));

            return;
        }
    }
}
