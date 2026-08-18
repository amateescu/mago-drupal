<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports empty hook_install() and hook_uninstall() implementations.
 *
 * Ports Drupal.Semantics.EmptyInstall.
 */
final class EmptyInstallHookRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/empty-install-hook',
            name: 'Empty install hook',
            description: 'Reports hook_install() and hook_uninstall() implementations with an empty body.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = DrupalFile::fromSource($context->file);
        if (!$file->isInstall()) {
            return;
        }

        $name = Nodes::declaredName($context->file, $context->node);
        if ($name === null || !$file->implementsHook($name, 'install') && !$file->implementsHook($name, 'uninstall')) {
            return;
        }

        $body = Nodes::body($context->file, $context->node);
        if ($body === null || $context->file->getChildren($body) !== []) {
            return;
        }

        $context->report(Issue::new("{$name}() is empty and can be removed.", $context->node->span)->withHelp(
            'Drupal treats a missing installation hook the same as an empty one.',
        ));
    }
}
