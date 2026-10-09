<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;

use function str_starts_with;
use function strtolower;
use function trim;

/**
 * Reports a property declared with `var` or without a visibility keyword.
 *
 * Ports Drupal.Classes.PropertyDeclaration.VarUsed and the ScopeMissing
 * check of PSR2.Classes.PropertyDeclaration. PHP makes such a property
 * public. Drupal wants that written down.
 */
final class PropertyVisibilityRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/property-visibility',
            name: 'Property visibility',
            description: 'Reports properties declared with var or without an explicit visibility keyword.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::PlainProperty, NodeKind::HookedProperty],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $anchor = null;
        $variable = null;
        foreach ($file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::Keyword && strtolower($file->getText($child)) === 'var') {
                // Coder also reports the missing visibility here. One issue
                // is enough, since its fix covers both.
                $context->report(Issue::new(
                    'Declare the property with public, not var.',
                    $child->span,
                )->withEdit(TextEdit::replace($child->span, 'public')));

                return;
            }

            if ($child->kind === NodeKind::Modifier) {
                $modifier = strtolower(trim($file->getText($child)));
                if (self::isVisibility($modifier)) {
                    return;
                }

                // Drupal writes the visibility after final and abstract, and
                // before static and readonly.
                if ($modifier === 'static' || $modifier === 'readonly') {
                    $anchor ??= $child;
                }

                continue;
            }

            if ($child->kind !== NodeKind::AttributeList) {
                $anchor ??= $child;
            }

            if ($child->kind === NodeKind::PropertyItem) {
                $variable ??= $file->getFirstDescendant($child, NodeKind::DirectVariable);
            }
        }

        if ($anchor === null) {
            return;
        }

        $name = $variable === null ? '' : ' ' . $file->getText($variable);
        $context->report(Issue::new(
            "Declare the visibility of the property{$name}.",
            ($variable ?? $context->node)->span,
        )->withEdit(TextEdit::insert($anchor->span->start, text: 'public ')));
    }

    /**
     * Whether a modifier sets the visibility, `public(set)` and the other
     * set visibilities included.
     */
    private static function isVisibility(string $modifier): bool
    {
        return (
            str_starts_with($modifier, 'public')
            || str_starts_with($modifier, 'protected')
            || str_starts_with($modifier, 'private')
        );
    }
}
