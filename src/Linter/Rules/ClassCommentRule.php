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
 * Ports Drupal.Commenting.ClassComment. The `@file`-tagged exemption exists
 * because a single-class file's docblock is often written as the file
 * comment instead, which `drupal/file-comment` already covers.
 */
final class ClassCommentRule implements Rule
{
    private const KEYWORDS = [
        NodeKind::Class_->value => 'class',
        NodeKind::Interface->value => 'interface',
        NodeKind::Trait->value => 'trait',
        NodeKind::Enum->value => 'enum',
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
        $keyword = self::KEYWORDS[$context->node->kind->value];
        $closest = Docblocks::closest($context->file, $context->node);

        if ($closest === null) {
            $context->report(Issue::new("Missing {$keyword} doc comment.", $context->node->span));

            return;
        }

        if ($closest->kind !== TriviaKind::DocBlockComment) {
            $context->report(Issue::new("A {$keyword} comment must use \"/**\" style comments.", $context->node->span));

            return;
        }

        $tags = Docblocks::tags($context->file, $closest->span);
        foreach ($tags as $tag) {
            if ($tag->name !== 'file') {
                continue;
            }

            $context->report(Issue::new("Missing {$keyword} doc comment.", $context->node->span));

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
                "The {$keyword} comment should describe what the {$keyword} does, not just repeat its name.",
                $context->node->span,
            ));

            return;
        }
    }
}
