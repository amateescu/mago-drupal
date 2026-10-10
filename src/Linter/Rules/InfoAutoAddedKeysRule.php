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

use function str_contains;

/**
 * Reports the keys of an `.info.yml` file that drupal.org packaging adds.
 *
 * Ports Drupal.InfoFiles.AutoAddedKeys. The packaging script writes
 * `project`, `datestamp` and `version` into the info files of a release, so
 * the files in the repository must not have them.
 */
final class InfoAutoAddedKeysRule implements Rule
{
    private const KEYS = ['project', 'datestamp', 'version'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/info-auto-added-keys',
            name: 'Info file auto-added keys',
            description: 'Reports the keys of an .info.yml file that drupal.org packaging adds.',
            defaultLevel: Level::Warning,
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
        if ($info === null) {
            return;
        }

        foreach (self::KEYS as $key) {
            // Core keeps `version` in its info files. Packaging does not add
            // it there.
            if (
                ($info[$key] ?? null) === null
                || $key === 'version' && str_contains('/' . $context->file->path, '/core/')
            ) {
                continue;
            }

            $context->report(Issue::new(
                "Remove \"{$key}\" from the info file. drupal.org packaging adds it.",
                YamlFile::keyLine($context->file, $key) ?? YamlFile::firstLine($context->file),
            ));
        }
    }
}
