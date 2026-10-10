<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\YamlFile;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function ltrim;
use function preg_match;
use function rtrim;
use function strlen;

/**
 * Reports a dependency in an `.info.yml` file without its project name, as
 * in `- node` for `- drupal:node`.
 *
 * Ports DrupalPractice.InfoFiles.NamespacedDependency. Like Coder, the rule
 * reads the lines of a `dependencies:` block list, and skips a theme, which
 * Drupal lets name a dependency without its project.
 */
final class InfoNamespacedDependencyRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/info-namespaced-dependency',
            name: 'Info file dependency project',
            description: 'Reports a dependency in an .info.yml file without its project name.',
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
        if ($info === null || ($info['type'] ?? null) === 'theme') {
            return;
        }

        $lines = YamlFile::lines($context->file);
        foreach ($lines as $index => [$text]) {
            if (preg_match('/^dependencies:/', $text) !== 1) {
                continue;
            }

            // The list runs while each line is a dependency or a comment.
            for ($next = $index + 1; $next < count($lines); ++$next) {
                [$line, $offset] = $lines[$next];
                if (preg_match('/^\s+- [^:]+\s*$/', $line) === 1) {
                    $this->report($context, $line, $offset);

                    continue;
                }

                if (preg_match('/^\s+- [^:]+:[^:]+\s*$/', $line) !== 1 && preg_match('/^\s*#/', $line) !== 1) {
                    break;
                }
            }
        }
    }

    /**
     * Reports the dependency on one line.
     */
    private function report(LintContext $context, string $line, int $offset): void
    {
        $item = rtrim($line);
        $name = ltrim($item, characters: " \t-");
        $start = $offset + strlen($item) - strlen($name);

        $context->report(Issue::new(
            "Prefix the dependency with the name of its project, as in drupal:{$name}.",
            new Span($start, $offset + strlen($item)),
        ));
    }
}
