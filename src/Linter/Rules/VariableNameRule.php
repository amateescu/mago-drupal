<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function preg_match;
use function substr;

/**
 * Reports a variable whose name starts with a capital letter.
 *
 * Ports Drupal.NamingConventions.ValidVariableName.LowerStart. Each
 * occurrence is reported, as Coder does. Mago's `variable-name` checks only
 * assignments and parameters.
 */
final class VariableNameRule implements Rule
{
    /**
     * The items of a property declaration. drupal/property-name checks
     * their names.
     */
    private const PROPERTY_ITEMS = [NodeKind::PropertyConcreteItem, NodeKind::PropertyAbstractItem];

    /**
     * The strings whose variables Coder skips. phpcs reads a double-quoted
     * string or a heredoc as one token.
     */
    private const STRINGS = [NodeKind::InterpolatedString, NodeKind::DocumentString];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/variable-name',
            name: 'Variable name',
            description: 'Reports variables whose name starts with a capital letter.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // The Program snapshot has the whole file, so the walk up from a
            // variable reaches the strings around it.
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        // Nearly every file doesn't have such a name, and the text says so
        // without reading the nodes.
        if (preg_match('/\$(?!GLOBALS\b)[A-Z]/', $file->contents) !== 1) {
            return;
        }

        foreach ($file->getNodes(NodeKind::DirectVariable) as $variable) {
            $name = substr($file->getText($variable), offset: 1);
            if ($name === 'GLOBALS' || preg_match('/^[A-Z]/', $name) !== 1 || self::isSkipped($file, $variable)) {
                continue;
            }

            $context->report(Issue::new(
                "Start the variable name \${$name} with a lower-case letter.",
                $variable->span,
            )->withHelp('Drupal writes variable names in $lowerCamelCase or $snake_case.'));
        }
    }

    /**
     * Whether Coder skips the variable: a property name, a static property
     * or method name after `::`, or a variable inside a double-quoted string
     * or a heredoc.
     */
    private static function isSkipped(SourceFile $file, Node $variable): bool
    {
        $start = $variable->span->start;
        if ($start >= 2 && substr($file->contents, $start - 2, length: 2) === '::') {
            return true;
        }

        $parent = $file->getParent($variable);
        if ($parent !== null && in_array($parent->kind, self::PROPERTY_ITEMS, strict: true)) {
            return true;
        }

        while ($parent !== null) {
            if (in_array($parent->kind, self::STRINGS, strict: true)) {
                return true;
            }

            $parent = $file->getParent($parent);
        }

        return false;
    }
}
