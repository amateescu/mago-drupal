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
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function count;

/**
 * Reports an exception message wrapped in t().
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
            description: 'Reports an exception message passed through t().',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Throw],
        );
    }

    public function lint(LintContext $context): void
    {
        // A match has `t` directly before a parenthesis, as a plain call or
        // as a method selector. The second branch keeps a file with a
        // function import, for the case of an aliased import.
        $this->gate ??= new FileGate(pattern: '/(?<!\w)t\s*\(|\buse\s[^;]*\bfunction\b/i');
        if (!$this->gate->passes($context->file)) {
            return;
        }

        $new = self::firstNew($context->file, $context->node);
        if ($new === null) {
            return;
        }

        // An anonymous class node also holds the class body. Coder reads
        // only the first parentheses after `new`, so only the constructor
        // arguments count.
        if ($new->kind === NodeKind::AnonymousClass) {
            $new = self::constructorArguments($context->file, $new);
            if ($new === null) {
                return;
            }
        }

        $call = Calls::findFirst($context->file, $new, ['t']);
        if ($call === null) {
            return;
        }

        $context->report(Issue::new('Do not translate an exception message.', $call->span)->withHelp(
            'Developers read exception text in logs. A translation hides the original text.',
        ));
    }

    /**
     * Returns the first `new` expression in a subtree, in source order.
     *
     * `new class(...)` is an AnonymousClass node, not an Instantiation.
     */
    private static function firstNew(SourceFile $file, Node $node): ?Node
    {
        $stack = [$node];
        while (($current = array_pop($stack)) !== null) {
            if ($current->kind === NodeKind::Instantiation || $current->kind === NodeKind::AnonymousClass) {
                return $current;
            }

            $children = $file->getChildren($current);
            for ($index = count($children) - 1; $index >= 0; --$index) {
                $stack[] = $children[$index];
            }
        }

        return null;
    }

    /**
     * Returns the constructor argument list of an anonymous class, or NULL.
     *
     * The list is a direct child. Without one, a search over all
     * descendants reaches into the class body.
     */
    private static function constructorArguments(SourceFile $file, Node $class): ?Node
    {
        foreach ($file->getChildren($class) as $child) {
            if ($child->kind === NodeKind::PartialArgumentList) {
                return $child;
            }
        }

        return null;
    }
}
