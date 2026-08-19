<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;

use function array_map;
use function implode;
use function preg_quote;

/**
 * Base for rules that fire on a call to one of a fixed set of names.
 *
 * @internal
 */
abstract class CallRule implements Rule
{
    /** @var null|array<string, true> */
    private ?array $wanted = null;

    private ?FileGate $gate = null;

    /**
     * Call names this rule reacts to, matched case-insensitively.
     *
     * @return list<string>
     */
    abstract protected function names(): array;

    /**
     * Checks one matched call. $name is the matched name, normalized.
     */
    abstract protected function inspect(LintContext $context, CallExpression $call, string $name): void;

    public function lint(LintContext $context): void
    {
        $this->gate ??= self::buildGate($this->names());
        if (!$this->gate->passes($context->file)) {
            return;
        }

        // The wanted set is normalized once per rule, not once per node.
        $this->wanted ??= Calls::normalizeAll($this->names());

        $name = Calls::matchWanted($context->file, $context->node, $this->wanted);
        if ($name === null) {
            return;
        }

        $this->inspect($context, CallExpression::fromNode($context->file, $context->node), $name);
    }

    /**
     * Returns the value of an argument, read positionally or by name.
     *
     * PHP binds named arguments by parameter name, so a rule that passes
     * $parameter also matches the `foo(name: $value)` spelling.
     */
    protected function argument(
        LintContext $context,
        CallExpression $call,
        int $index,
        ?string $parameter = null,
    ): ?Node {
        if ($parameter !== null) {
            foreach ($call->arguments as $argument) {
                if ($argument->name === $parameter) {
                    return Values::unwrap($context->file, $argument->value);
                }
            }
        }

        return Calls::positionalArguments($context->file, $call)[$index] ?? null;
    }

    /**
     * Builds the file gate from the rule's call names.
     *
     * Any match puts a wanted name in the source right before an opening
     * parenthesis, whether written as a plain call, a method selector, or
     * a fully qualified call. The one exception is a call through an
     * aliased `use function` import, which the second branch keeps in by
     * passing any file with a function import. Anchoring on the
     * parenthesis lets even one-letter names like `l` gate their files; a
     * comment between the callee and its parenthesis would defeat the
     * anchor, and nothing writes that.
     *
     * @param list<string> $names
     */
    private static function buildGate(array $names): FileGate
    {
        $alternation = implode('|', array_map(static fn(string $name): string => preg_quote(
            $name,
            delimiter: '/',
        ), $names));

        return new FileGate(pattern: "/(?<!\\w)(?:{$alternation})\\s*\\(|\\buse\\s[^;]*\\bfunction\\b/i");
    }
}
