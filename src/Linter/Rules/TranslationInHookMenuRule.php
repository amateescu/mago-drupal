<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Linter\HookTranslationRule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports t() calls inside hook_menu().
 *
 * Ports Drupal.Semantics.TInHookMenu. Only fires on Drupal 7 era code, since
 * hook_menu() was replaced by routing YAML.
 */
final class TranslationInHookMenuRule extends HookTranslationRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/t-in-hook-menu',
            name: 'Translation in hook_menu()',
            description: 'Reports t() calls inside hook_menu(), which Drupal translates on render instead.',
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
        return 'Menu titles are translated when the item is rendered, so translating them here is too early.';
    }
}
