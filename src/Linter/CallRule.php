<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Base for rules that fire on a call to one of a fixed set of names.
 *
 * @internal
 */
abstract class CallRule implements Rule
{
    /** @var null|array<string, true> */
    private ?array $wanted = null;

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
        $name = $this->matchedName($context);
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
     * Returns the normalized wanted name this call matches, or NULL.
     *
     * Mago resolves an unimported unqualified call into the current
     * namespace, but PHP falls back to the global function at runtime, so
     * only an imported resolution is trusted over the written name. That is
     * how Mago's own rules match global functions too.
     */
    private function matchedName(LintContext $context): ?string
    {
        // The wanted set is normalized once per rule, not once per node.
        $this->wanted ??= Calls::normalizeAll($this->names());

        // An imported resolution wins outright: when it misses the wanted
        // set there is no fall-through to the written name.
        $name = null;
        if ($context->node->kind === NodeKind::FunctionCall) {
            $resolved = $context->getResolvedName();
            if ($resolved !== null && $resolved->imported) {
                $name = $resolved->name;
            }
        }

        $name ??= Calls::name($context->file, $context->node);
        if ($name === null) {
            return null;
        }

        $name = Calls::normalize($name);

        return $this->wanted[$name] ?? false ? $name : null;
    }
}
