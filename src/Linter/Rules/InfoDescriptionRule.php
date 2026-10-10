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
 * Reports an extension's `.info.yml` file with no description or an empty
 * one.
 *
 * Ports DrupalPractice.InfoFiles.Description.
 */
final class InfoDescriptionRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/info-description',
            name: 'Info file description',
            description: 'Reports an .info.yml file with no description or an empty one.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $info = YamlFile::extensionInfo($context->file);
        if ($info === null) {
            return;
        }

        if (($info['description'] ?? null) === null) {
            $context->report(Issue::new(
                'The info file has no "description" key.',
                YamlFile::firstLine($context->file),
            ));

            return;
        }

        if ($info['description'] === '') {
            $context->report(Issue::new(
                'The "description" of the info file is empty.',
                YamlFile::keyLine($context->file, 'description') ?? YamlFile::firstLine($context->file),
            ));
        }
    }
}
