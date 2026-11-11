<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Drupal\YamlFile;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function str_contains;
use function strpos;

/**
 * Reports the keys of an `.info.yml` file that drupal.org packaging adds.
 *
 * Ports Drupal.InfoFiles.AutoAddedKeys. The packaging script writes
 * `project`, `datestamp` and `version` into the info files of a release, so
 * the files in the repository must not have them. The script puts a comment
 * above the keys that it writes, and a key below that comment is not
 * reported. A site that lints its installed modules has those keys in every
 * info file.
 *
 * @internal
 */
final class InfoAutoAddedKeysRule implements Rule
{
    private const KEYS = ['project', 'datestamp', 'version'];

    /**
     * The start of the comment line that the packaging script writes above
     * its keys. A date follows it.
     */
    private const PACKAGING_COMMENT = "\n# Information added by Drupal.org packaging script";

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/info-auto-added-keys',
            name: 'Info file auto-added keys',
            description: 'Reports a key of an .info.yml file that drupal.org packaging adds.',
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

        $packaged = strpos($context->file->contents, needle: self::PACKAGING_COMMENT);

        foreach (self::KEYS as $key) {
            if (
                ($info[$key] ?? null) === null
                || $key === 'version' && str_contains('/' . $context->file->path, '/core/')
            ) {
                continue;
            }

            $line = YamlFile::keyLine($context->file, $key);
            if ($packaged !== false && $line !== null && $line->start > $packaged) {
                continue;
            }

            $context->report(Issue::new(
                "Remove \"{$key}\" from the info file. drupal.org packaging adds it.",
                $line ?? YamlFile::firstLine($context->file),
            ));
        }
    }
}
