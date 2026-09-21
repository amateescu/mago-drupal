<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Linter\HookTranslationRule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports a t() call inside hook_schema().
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
            description: 'Reports a t() call inside hook_schema(). Drupal never shows those strings to users.',
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
        return 'A schema description is developer documentation. A translation only adds work for translators.';
    }
}
