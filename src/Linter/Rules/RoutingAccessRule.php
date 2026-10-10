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

use function preg_match;
use function rtrim;
use function strlen;
use function strspn;

/**
 * Reports an open route and a route that asks for the "access
 * administration pages" permission in a `.routing.yml` file.
 *
 * Ports DrupalPractice.Yaml.RoutingAccess. Like Coder, the rule reads the
 * lines, so it matches `_access: 'TRUE'` and the permission as written with
 * single quotes.
 */
final class RoutingAccessRule implements Rule
{
    private const OPEN = 'The route is open to everyone. Say why in a comment on the line above.';

    private const ADMINISTRATION = 'Use "administer site configuration" for an administration page.';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/routing-access',
            name: 'Routing access',
            description: 'Reports an open route without a comment, and the "access administration pages" permission.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!YamlFile::is($context->file, '.routing.yml')) {
            return;
        }

        $previous = null;
        foreach (YamlFile::lines($context->file) as [$line, $offset]) {
            // A comment on the line above says why the route is open.
            if (
                preg_match("/^\\s+_access: 'TRUE'/", $line) === 1
                && $previous !== null
                && preg_match('/^\s*#/', $previous) !== 1
            ) {
                $context->report(Issue::new(self::OPEN, self::span($line, $offset)));
            }

            if (preg_match("/^\\s+_permission: 'access administration pages'/", $line) === 1) {
                $context->report(Issue::new(self::ADMINISTRATION, self::span($line, $offset))->withHelp(
                    '"access administration pages" lets a user view the page, not change settings.',
                ));
            }

            $previous = $line;
        }
    }

    /**
     * The span of a line without its indent and its line break.
     */
    private static function span(string $line, int $offset): Span
    {
        return new Span($offset + strspn($line, characters: " \t"), $offset + strlen(rtrim($line)));
    }
}
