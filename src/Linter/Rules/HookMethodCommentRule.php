<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\AttributeArguments;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\HookDocblock;
use amateescu\MagoDrupal\Internal\HookSummary;
use amateescu\MagoDrupal\Internal\LiteralText;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function strcasecmp;

/**
 * Checks the "Implements hook_x()." docblock convention on a `#[Hook]` method.
 *
 * Coder doesn't read methods. A `#[Hook]` method is a hook implementation
 * whatever its name. Drupal's documentation standard predates OOP hooks: it
 * asks a hook implementation for an "Implements hook_x()." summary and does
 * not mention methods. Core writes that summary on its `#[Hook]` methods, so
 * the rule asks for it. A method's docblock may sit above the attributes.
 *
 * Finding the attribute through its list and the docblock on either side of it
 * needs many branches.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class HookMethodCommentRule implements Rule
{
    private const HOOK_CLASS = 'Drupal\Core\Hook\Attribute\Hook';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/hook-method-comment',
            name: 'Hook method comment',
            description: 'Checks the "Implements hook_x()." docblock convention on a #[Hook] method.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $method = $context->node;
        $hook = self::hookAttribute($file, $method);
        $docblock = $hook === null ? null : Docblocks::attachedTo($file, Docblocks::commentAnchor($file, $method));
        $summary = $docblock === null ? [] : HookDocblock::summary($file, $docblock);
        if ($hook === null || $docblock === null || $summary === []) {
            return;
        }

        if (HookSummary::check($context, $docblock, $summary)) {
            return;
        }

        $argument = AttributeArguments::first($file, $file->getChildren($hook)[1] ?? null, parameter: 'hook');
        $name = $argument === null ? null : LiteralText::of($file, $argument);
        $issue = Issue::new(
            'Document a hook implementation with "Implements hook_foo().".',
            HookSummary::span($summary),
        );
        if ($name !== null) {
            $issue = $issue->withHelp("This method implements hook_{$name}().");
        }

        $context->report($issue);
    }

    /**
     * The method's first `#[Hook]` attribute, or null. The name resolves
     * through imports and aliases, and PHP ignores its case.
     */
    private static function hookAttribute(SourceFile $file, Node $method): ?Node
    {
        foreach ($file->getChildren($method) as $list) {
            if ($list->kind !== NodeKind::AttributeList) {
                continue;
            }

            foreach ($file->getChildren($list) as $attribute) {
                $name = $file->getChildren($attribute)[0] ?? null;
                if ($name !== null && strcasecmp(Nodes::resolved($file, $name) ?? '', self::HOOK_CLASS) === 0) {
                    return $attribute;
                }
            }
        }

        return null;
    }
}
