<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\FileGate;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Reports exception messages wrapped in t().
 *
 * Ports DrupalPractice.General.ExceptionT. Exception text goes to logs and
 * developers, not to site visitors.
 */
final class TranslatedExceptionRule implements Rule
{
    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/translated-exception',
            name: 'Translated exception',
            description: 'Reports exception messages passed through t().',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Throw],
        );
    }

    public function lint(LintContext $context): void
    {
        // A match writes `t` right before a parenthesis, as a plain call
        // or a method selector; the second branch keeps files with a
        // function import in for the aliased-import case.
        $this->gate ??= new FileGate(pattern: '/(?<!\w)t\s*\(|\buse\s[^;]*\bfunction\b/i');
        if (!$this->gate->passes($context->file)) {
            return;
        }

        $instantiation = $context->file->getFirstDescendant($context->node, NodeKind::Instantiation);
        if ($instantiation === null) {
            return;
        }

        $call = Calls::findFirst($context->file, $instantiation, ['t']);
        if ($call === null) {
            return;
        }

        $context->report(Issue::new('Exception messages should not be translated.', $call->span)->withHelp(
            'Exception text is read by developers in logs, so translating it hides the original.',
        ));
    }
}
