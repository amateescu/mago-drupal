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

use function is_array;

/**
 * Reports a `dependencies` key of an `.info.yml` file that is not a list.
 *
 * Ports Drupal.InfoFiles.DependenciesArray.
 */
final class InfoDependenciesArrayRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/info-dependencies-array',
            name: 'Info file dependencies list',
            description: 'Reports a dependencies key of an .info.yml file that is not a list.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!YamlFile::is($context->file, '.info.yml')) {
            return;
        }

        $info = YamlFile::parse($context->file);
        if (($info['dependencies'] ?? null) === null || is_array($info['dependencies'])) {
            return;
        }

        $context->report(Issue::new(
            'The "dependencies" key of the info file must hold a list.',
            YamlFile::keyLine($context->file, 'dependencies') ?? YamlFile::firstLine($context->file),
        )->withHelp('Write each dependency on its own line, as in "  - drupal:node".'));
    }
}
