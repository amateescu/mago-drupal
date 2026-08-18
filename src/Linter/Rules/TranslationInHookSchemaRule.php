<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Linter\HookTranslationRule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports t() calls inside hook_schema().
 *
 * Ports Drupal.Semantics.TInHookSchema.
 */
final class TranslationInHookSchemaRule extends HookTranslationRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/t-in-hook-schema',
            name: 'Translation in hook_schema()',
            description: 'Reports t() calls inside hook_schema(), where the strings are never shown to users.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function],
        );
    }

    protected function hook(): string
    {
        return 'schema';
    }

    protected function extension(): string
    {
        return 'install';
    }

    protected function help(): string
    {
        return 'Schema descriptions are developer documentation, so translating them only adds work for translators.';
    }
}
