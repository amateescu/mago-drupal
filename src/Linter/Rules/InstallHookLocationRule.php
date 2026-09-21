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
 * Reports install-time hooks declared in a `.module` file.
 *
 * Ports Drupal.Semantics.InstallHooks. Drupal loads `.install` only during
 * install and update runs. Drupal never calls a hook placed in `.module`.
 */
final class InstallHookLocationRule implements Rule
{
    private const INSTALL_HOOKS = ['install', 'uninstall', 'requirements', 'schema', 'enable', 'disable'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/install-hook-location',
            name: 'Install hook location',
            description: 'Reports install-time hooks declared in a .module file instead of a .install file.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = DrupalFile::fromSource($context->file);
        if (!$file->isModule()) {
            return;
        }

        $name = Nodes::declaredName($context->file, $context->node);
        if ($name === null) {
            return;
        }

        foreach (self::INSTALL_HOOKS as $hook) {
            if (!$file->implementsHook($name, $hook)) {
                continue;
            }

            $context->report(Issue::new(
                "{$name}() is an install hook. Declare it in {$file->name}.install.",
                $context->node->span,
            )->withHelp('Drupal loads the .install file only during install and update runs.'));

            return;
        }
    }
}
