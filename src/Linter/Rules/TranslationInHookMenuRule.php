<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Linter\HookTranslationRule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports a t() call inside hook_menu().
 *
 * Ports Drupal.Semantics.TInHookMenu. It reports only Drupal 7 era code,
 * because routing YAML replaces hook_menu().
 */
final class TranslationInHookMenuRule extends HookTranslationRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/t-in-hook-menu',
            name: 'Translation in hook_menu()',
            description: 'Reports a t() call inside hook_menu(). Drupal translates the strings when it renders them.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function],
        );
    }

    protected function hook(): string
    {
        return 'menu';
    }

    protected function extension(): string
    {
        return 'module';
    }

    protected function help(): string
    {
        return 'Drupal translates a menu title when it renders the item. A translation here is too early.';
    }
}
