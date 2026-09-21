<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DrupalFile;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function in_array;
use function str_starts_with;

/**
 * Reports module globals that do not have the underscore prefix.
 *
 * Ports Drupal.NamingConventions.ValidGlobal. Globals share one namespace. A
 * leading underscore marks the globals that a module owns.
 */
final class GlobalVariableRule implements Rule
{
    /**
     * Globals that Drupal core owns. A module may read them.
     */
    private const CORE_GLOBALS = [
        '$argc',
        '$argv',
        '$base_insecure_url',
        '$base_path',
        '$base_root',
        '$base_secure_url',
        '$base_theme_info',
        '$base_url',
        '$channel',
        '$conf',
        '$config',
        '$config_directories',
        '$cookie_domain',
        '$databases',
        '$db_prefix',
        '$db_type',
        '$db_url',
        '$drupal_hash_salt',
        '$drupal_test_info',
        '$element',
        '$forum_topic_list_header',
        '$image',
        '$install_state',
        '$installed_profile',
        '$is_https',
        '$is_https_mock',
        '$item',
        '$items',
        '$language',
        '$language_content',
        '$language_url',
        '$locks',
        '$menu_admin',
        '$multibyte',
        '$pager_limits',
        '$pager_page_array',
        '$pager_total',
        '$pager_total_items',
        '$tag',
        '$theme',
        '$theme_engine',
        '$theme_info',
        '$theme_key',
        '$theme_path',
        '$timers',
        '$update_free_access',
        '$update_rewrite_settings',
        '$user',
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/global-variable',
            name: 'Global variable name',
            description: "Reports module globals that do not start with an underscore and the module's name.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Global],
        );
    }

    public function lint(LintContext $context): void
    {
        // The naming convention covers the globals that a module owns. The
        // rule examines only the procedural entry files of the module.
        $file = DrupalFile::fromSource($context->file);
        if (!$file->isModule() && !$file->isInstall()) {
            return;
        }

        foreach ($context->file->getDescendants($context->node, NodeKind::DirectVariable) as $variable) {
            $name = $context->file->getText($variable);
            if (in_array($name, self::CORE_GLOBALS, strict: true) || str_starts_with($name, '$_')) {
                continue;
            }

            $context->report(Issue::new(
                "Start the global {$name} with an underscore and the module's name.",
                $variable->span,
            )->withHelp('Globals share one namespace across every module on the site.'));
        }
    }
}
