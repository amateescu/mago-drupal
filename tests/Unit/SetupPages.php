<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;

/**
 * Renders the generated parts of the setup pages under `docs/setup/`.
 */
final class SetupPages
{
    private function __construct() {}

    /**
     * Renders the `[linter.rules]` block that turns off the rules `--core`
     * turns off.
     */
    public static function coreRules(): string
    {
        $lines = '';
        foreach (DrupalExtension::create(core: true)->linterRules as $rule) {
            $definition = $rule->getDefinition();
            if ($definition->defaultEnabled) {
                continue;
            }

            $lines .= "\"{$definition->code}\" = { enabled = false }\n";
        }

        return "```toml\n[linter.rules]\n{$lines}```";
    }
}
