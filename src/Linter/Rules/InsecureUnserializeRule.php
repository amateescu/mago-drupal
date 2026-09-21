<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function ltrim;
use function preg_match;
use function stripos;
use function strtolower;
use function trim;

/**
 * Reports `unserialize()` calls that do not limit the classes they accept.
 *
 * Ports DrupalPractice.FunctionCalls.InsecureUnserialize. Without
 * `allowed_classes`, the payload decides which objects PHP builds, and
 * their destructors run.
 *
 * The rule makes one exemption that the sniff does not make. A payload that
 * the same function built with `serialize()` cannot hold a foreign class.
 * The rule therefore skips `unserialize(serialize($x))`. It also skips a
 * local variable whose every assignment is a `serialize()` call. That is
 * the shape of a serialization test.
 *
 * The rule reads the calls from the file's target list, the same way that
 * `DeprecationMessageRule` does. That is why `FunctionCall` is a declared
 * target. The per-call dispatches do nothing.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class InsecureUnserializeRule implements Rule
{
    private const FUNCTION_LIKE = [NodeKind::Function, NodeKind::Method, NodeKind::Closure, NodeKind::ArrowFunction];

    private const HELP = 'Pass ["allowed_classes" => FALSE], or list the classes that the payload may build. JSON does not have this problem.';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/insecure-unserialize',
            name: 'Insecure unserialize',
            description: 'Reports unserialize() calls that do not limit the allowed classes.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        if (stripos($context->file->contents, needle: 'unserialize') === false) {
            return;
        }

        $calls = Calls::findFunctionsInTargets($context->file, within: null, names: ['unserialize'])['unserialize']
        ?? [];
        foreach ($calls as $call) {
            $problem = $this->problem($context->file, $call);
            if ($problem === null) {
                continue;
            }

            $context->report(Issue::new($problem, $call->span)->withHelp(self::HELP));
        }
    }

    /**
     * Returns what is wrong with a call's options, if anything.
     */
    private function problem(SourceFile $file, Node $call): ?string
    {
        $expression = CallExpression::fromNode($file, $call);
        if ($this->roundTrips($file, $expression)) {
            return null;
        }

        $options = Calls::argument($file, $expression, index: 1, parameter: 'options');
        if ($options === null) {
            // A spread hides what lands in the options position. The rule
            // skips the call rather than report on a guess.
            return Calls::isUnpacked($expression)
                ? null
                : 'An unserialize() call without options accepts every class in the payload.';
        }

        // The ported sniff examines the second argument's tokens for the
        // key. It reports a variable or a call there too. The message
        // says what the rule can and cannot see.
        if ($options->kind !== NodeKind::Array) {
            return 'The unserialize() options are not an array literal. The rule cannot check allowed_classes.';
        }

        foreach ($file->getDescendants($options, NodeKind::KeyValueArrayElement) as $element) {
            $children = $file->getChildren($element);
            $key = $children[0] ?? null;
            $value = $children[1] ?? null;
            if (
                $key === null
                || $value === null
                || trim($file->getText($key), characters: '\'"') !== 'allowed_classes'
            ) {
                continue;
            }

            return strtolower(trim($file->getText($value))) === 'true'
                ? 'allowed_classes is TRUE. That accepts every class in the payload.'
                : null;
        }

        return 'The unserialize() options do not set allowed_classes.';
    }

    /**
     * Whether `serialize()` built the payload in the same function.
     */
    private function roundTrips(SourceFile $file, CallExpression $expression): bool
    {
        $payload = Calls::argument($file, $expression, index: 0, parameter: 'data');
        if ($payload === null) {
            return false;
        }

        if (self::isSerializeCall($file, $payload)) {
            return true;
        }

        $payload = self::unwrapVariable($file, $payload);

        return $payload->kind === NodeKind::DirectVariable && $this->onlySerializedInto($file, $payload);
    }

    /**
     * Whether a local variable is written only by plain assignments of a
     * `serialize()` call, in the function that reads it.
     *
     * A parameter of the same name has a value of its own. It does not
     * qualify, even when the function assigns it again.
     */
    private function onlySerializedInto(SourceFile $file, Node $variable): bool
    {
        $scope = $this->enclosingFunctionLike($file, $variable);
        if ($scope === null) {
            return false;
        }

        $name = $file->getText($variable);
        foreach ($file->getChildren($scope) as $child) {
            if (
                $child->kind === NodeKind::FunctionLikeParameterList
                && preg_match('/\\' . $name . '\\b/', $file->getText($child)) === 1
            ) {
                return false;
            }
        }

        $assigned = false;
        foreach ($file->getDescendants($scope, NodeKind::Assignment) as $assignment) {
            $parts = $file->getChildren($assignment);
            $target = self::unwrapVariable($file, Values::unwrap($file, $parts[0]));
            if ($target->kind !== NodeKind::DirectVariable || $file->getText($target) !== $name) {
                continue;
            }

            $operator = $parts[1] ?? null;
            $value = $parts[count($parts) - 1];
            if (
                $operator === null
                || trim($file->getText($operator)) !== '='
                || !self::isSerializeCall($file, Values::unwrap($file, $value))
            ) {
                return false;
            }

            $assigned = true;
        }

        return $assigned;
    }

    private function enclosingFunctionLike(SourceFile $file, Node $node): ?Node
    {
        $parent = $file->getParent($node);
        while ($parent !== null && !in_array($parent->kind, self::FUNCTION_LIKE, strict: true)) {
            $parent = $file->getParent($parent);
        }

        return $parent;
    }

    /**
     * A variable arrives as `Variable -> DirectVariable`. This removes the
     * wrapper.
     */
    private static function unwrapVariable(SourceFile $file, Node $node): Node
    {
        if ($node->kind !== NodeKind::Variable) {
            return $node;
        }

        return $file->getChildren($node)[0] ?? $node;
    }

    private static function isSerializeCall(SourceFile $file, Node $node): bool
    {
        if ($node->kind !== NodeKind::FunctionCall) {
            return false;
        }

        $name = Calls::name($file, $node);

        return $name !== null && strtolower(ltrim($name, characters: '\\')) === 'serialize';
    }
}
