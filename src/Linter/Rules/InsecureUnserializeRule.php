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
 * the shape of a serialization test. Only an assignment in the variable's
 * own scope counts, so one in a nested closure doesn't. For a read inside an
 * arrow function, an outer assignment that comes after the arrow function
 * doesn't count either.
 *
 * The rule reads the calls from the file's target list, the same way that
 * `DeprecationMessageRule` does. That is why `FunctionCall` and
 * `FunctionPartialApplication` are declared targets. The per-call dispatches
 * don't do anything. A first-class callable, `unserialize(...)`, passes the
 * call on with no options, so it is reported too.
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
            description: "Reports an unserialize() call that doesn't limit the allowed classes.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::FunctionCall, NodeKind::FunctionPartialApplication],
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

        $wanted = Calls::normalizeAll(['unserialize']);
        foreach ($context->file->getTargetNodes() as $node) {
            if (
                $node->kind !== NodeKind::FunctionPartialApplication
                || Calls::matchWanted($context->file, $node, $wanted) === null
            ) {
                continue;
            }

            $context->report(Issue::new(
                'unserialize(...) passes the call on with no options, so it accepts every class.',
                $node->span,
            )->withHelp(self::HELP));
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
        if ($options->kind !== NodeKind::Array && $options->kind !== NodeKind::LegacyArray) {
            return 'The unserialize() options are not an array literal. The rule cannot check allowed_classes.';
        }

        // Only a key of the options array itself counts. PHP doesn't read
        // an `allowed_classes` key of a nested array.
        foreach ($file->getChildren($options) as $wrapper) {
            $element = $wrapper->kind === NodeKind::ArrayElement ? $file->getChildren($wrapper)[0] ?? null : $wrapper;
            if ($element === null || $element->kind !== NodeKind::KeyValueArrayElement) {
                continue;
            }

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

            // `\TRUE` names the same constant as `TRUE`, and `(TRUE)` is the
            // same value.
            $written = trim($file->getText(self::unparenthesize($file, $value)));

            return strtolower(ltrim($written, characters: '\\')) === 'true'
                ? 'allowed_classes is TRUE. That accepts every class in the payload.'
                : null;
        }

        return 'The unserialize() options do not set allowed_classes.';
    }

    /**
     * The value inside any parentheses around it.
     */
    private static function unparenthesize(SourceFile $file, Node $value): Node
    {
        $value = Values::unwrap($file, $value);
        while ($value->kind === NodeKind::Parenthesized) {
            $inner = $file->getChildren($value)[0] ?? null;
            if ($inner === null) {
                break;
            }

            $value = Values::unwrap($file, $inner);
        }

        return $value;
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
     *
     * An arrow function reads the variables of the function around it, so the
     * variable of a read inside one belongs to the first function, method or
     * closure above it. Only an assignment in that scope, or in an arrow
     * function around the read, counts as building the payload. An arrow
     * function copies the outer value when PHP creates it, so an assignment
     * in an outer scope counts only when it comes before the arrow function.
     * An assignment in a nested closure or arrow function writes to a
     * variable of its own. Any other write to the name still disqualifies it,
     * because a closure that imports the variable by reference writes to the
     * outer one.
     */
    private function onlySerializedInto(SourceFile $file, Node $variable): bool
    {
        $name = $file->getText($variable);
        // Maps each scope that the read sees to the offset where an
        // assignment in it must end. That is the start of the arrow function
        // inside it that holds the read, and no limit in the innermost scope.
        $readScopes = [];
        $cutoff = PHP_INT_MAX;
        $scope = $this->enclosingFunctionLike($file, $variable);
        while ($scope !== null) {
            if (self::declaresParameter($file, $scope, $name)) {
                return false;
            }

            $readScopes[$scope->id] = $cutoff;
            if ($scope->kind !== NodeKind::ArrowFunction) {
                break;
            }

            $cutoff = $scope->span->start;
            $scope = $this->enclosingFunctionLike($file, $scope);
        }

        if ($scope === null) {
            return false;
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

            $limit = $readScopes[$this->enclosingFunctionLike($file, $assignment)->id ?? -1] ?? null;
            $assigned = $assigned || $limit !== null && $assignment->span->end <= $limit;
        }

        return $assigned;
    }

    /**
     * Whether the function-like has a parameter with the variable's name.
     */
    private static function declaresParameter(SourceFile $file, Node $scope, string $name): bool
    {
        foreach ($file->getChildren($scope) as $child) {
            if (
                $child->kind === NodeKind::FunctionLikeParameterList
                && preg_match('/\\' . $name . '\\b/', $file->getText($child)) === 1
            ) {
                return true;
            }
        }

        return false;
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
