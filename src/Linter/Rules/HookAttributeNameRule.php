<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\AttributeArguments;
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

use function str_starts_with;
use function strcasecmp;
use function strlen;
use function substr;

/**
 * Reports a Hook attribute whose hook name starts with hook_.
 *
 * Ports Drupal.Attributes.ValidHookName.HookPrefix. The attribute holds the
 * full hook name, so the prefix names a hook that nothing invokes. Removing
 * it changes which hook runs, so the rule has no fix.
 */
final class HookAttributeNameRule implements Rule
{
    private const HOOK_CLASS = 'Drupal\Core\Hook\Attribute\Hook';

    private const PREFIX = 'hook_';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/hook-attribute-name',
            name: 'Hook attribute name',
            description: 'Reports Hook attributes whose name starts with hook_.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Attribute],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $children = $file->getChildren($context->node);
        $name = $children[0] ?? null;
        if ($name === null || !$this->isHook($file, $name)) {
            return;
        }

        $argument = AttributeArguments::first($file, $children[1] ?? null, parameter: 'hook');
        if ($argument === null) {
            return;
        }

        $hook = LiteralText::of($file, $argument);
        if ($hook === null || $hook === self::PREFIX || !str_starts_with($hook, self::PREFIX)) {
            return;
        }

        $expected = substr($hook, offset: strlen(self::PREFIX));
        $context->report(Issue::new(
            "The hook name '{$hook}' must not start with 'hook_', use '{$expected}'.",
            $argument->span,
        )->withHelp('The attribute holds the whole hook name. A name with the prefix is never invoked.'));
    }

    /**
     * Whether the attribute name is Drupal's Hook class.
     *
     * PHP ignores case in class names, and a name can be an alias or fully
     * qualified, so the check reads the resolved name. A bare Hook that is
     * not imported from Drupal's class is another class.
     */
    private function isHook(SourceFile $file, Node $name): bool
    {
        return strcasecmp(Nodes::resolved($file, $name) ?? '', self::HOOK_CLASS) === 0;
    }
}
