<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\YamlFile;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports an extension's `.info.yml` file without a
 * `core_version_requirement` key.
 *
 * Ports DrupalPractice.InfoFiles.CoreVersionRequirement. A test module, with
 * `package: Testing`, may leave the key out.
 */
final class InfoCoreVersionRequirementRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/info-core-version-requirement',
            name: 'Info file core version requirement',
            description: 'Reports an .info.yml file without a core_version_requirement key.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $info = YamlFile::extensionInfo($context->file);
        if (
            $info === null
            || ($info['package'] ?? null) === 'Testing'
            || ($info['core_version_requirement'] ?? null) !== null
        ) {
            return;
        }

        $context->report(Issue::new(
            'The info file has no "core_version_requirement" key.',
            YamlFile::firstLine($context->file),
        )->withHelp('Drupal installs the extension only on the core versions that the key names.'));
    }
}
