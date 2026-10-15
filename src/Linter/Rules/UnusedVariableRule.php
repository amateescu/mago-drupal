<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FunctionScopes;
use amateescu\MagoDrupal\Internal\ScopeVariable;
use amateescu\MagoDrupal\Internal\VariableScopes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function sprintf;
use function str_ends_with;

/**
 * Reports a variable that a scope writes and never reads.
 *
 * Ports VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable with
 * the settings of Coder's DrupalPractice standard. A name that the scope
 * reads anywhere counts as used, before or after the write.
 */
final class UnusedVariableRule implements Rule
{
    /**
     * The files that core's `phpcs.xml.dist` leaves out of the check: the
     * hook documentation and the transliteration tables.
     */
    private const CORE_SKIPPED = '#(?:\.api\.php|(?:^|/)core/lib/Drupal/Component/Transliteration/data/[^/]*\.php)$#';

    /**
     * The message for each kind of variable.
     */
    private const MESSAGES = [
        ScopeVariable::LOCAL => 'The variable %s is assigned but never read.',
        ScopeVariable::STATIC => 'The static variable %s is never read.',
        ScopeVariable::GLOBAL => 'The global variable %s is never read or assigned.',
        ScopeVariable::BOUND => 'The closure imports %s in its use list but never reads it.',
    ];

    /**
     * The help for each kind of variable.
     */
    private const HELP = [
        ScopeVariable::LOCAL => 'Remove the assignment, or use the value.',
        ScopeVariable::STATIC => 'Remove the static declaration.',
        ScopeVariable::GLOBAL => 'Remove the variable from the global statement.',
        ScopeVariable::BOUND => 'Remove the variable from the use list.',
    ];

    /**
     * @param bool $core Skips the files that core's `phpcs.xml.dist` leaves
     *   out of the check.
     */
    public function __construct(
        private readonly bool $core = false,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/unused-variable',
            name: 'Unused variable',
            description: 'Reports a variable that is written and never read in its function, closure or file.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // Program has the whole file in the snapshot. The other kinds
            // put the scopes in the file's target nodes, and their own
            // dispatches do nothing.
            targets: [NodeKind::Program, ...FunctionScopes::REGIONS],
        );
    }

    public function lint(LintContext $context): void
    {
        $path = $context->file->path;

        // Coder's ruleset skips templates.
        if (
            $context->node->kind !== NodeKind::Program
            || str_ends_with($path, '.tpl.php')
            || $this->core && !self::checkedOnCore($path)
        ) {
            return;
        }

        foreach (VariableScopes::of($context->file, $context->node->id) as $variables) {
            foreach ($variables as $name => $variable) {
                $kind = $variable->kind();
                foreach ($variable->unusedWrites() as $span) {
                    $context->report(Issue::new(sprintf(self::MESSAGES[$kind], $name), $span)->withHelp(
                        self::HELP[$kind],
                    ));
                }
            }
        }
    }

    /**
     * Whether core's `phpcs.xml.dist` checks the file at $path for unused
     * variables.
     */
    public static function checkedOnCore(string $path): bool
    {
        return preg_match(self::CORE_SKIPPED, $path) !== 1;
    }
}
