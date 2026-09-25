<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Invocation;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\SourceFile;
use Throwable;

use function in_array;
use function rtrim;
use function trim;

/**
 * Finds the argument that fills a parameter of a call.
 *
 * Mago hands a hook the argument types in source order, which is the
 * parameter order only while every argument is positional. `getHandler(
 * handler_type: 'storage', entity_type_id: 'node')` puts the handler type
 * first, so a rule reading position 0 would check the wrong string.
 *
 * @internal
 */
final class Arguments
{
    private function __construct() {}

    /**
     * The type of the argument filling a parameter, or null when the call
     * does not pass it or its position cannot be known.
     *
     * @param int $position The parameter's position, counting from zero.
     * @param non-empty-list<string> $names The parameter's name, without the
     *   dollar sign. A hook targeting several methods names the parameter of
     *   each, since only one of them is the callee.
     */
    public static function type(NodeAnalysisContext $context, int $position, string ...$names): ?Type
    {
        $index = self::index($context->source, $context->node, $position, $names);

        return $index === null ? null : $context->argumentTypes[$index] ?? null;
    }

    /**
     * Where that argument sits in source order, or null when the call does
     * not pass it. A caller that has to tell an absent argument from one
     * whose type is unknown reads the type list itself.
     *
     * @param non-empty-list<string> $names Names of the parameter, as
     *   `type()` takes them.
     */
    public static function sourceIndex(NodeAnalysisContext $context, int $position, string ...$names): ?int
    {
        return self::index($context->source, $context->node, $position, $names);
    }

    /**
     * The source position of the argument filling a parameter.
     *
     * An argument unpacked from an array leaves every later position
     * unknown, so the answer is null rather than a guess. So does a node
     * Mago cannot read as a call, such as a first-class callable.
     *
     * @param array<array-key, string> $names
     */
    private static function index(SourceFile $file, Node $node, int $position, array $names): ?int
    {
        if (!in_array($node->kind, Calls::CALL_KINDS, strict: true)) {
            return null;
        }

        try {
            $call = CallExpression::fromNode($file, $node);
        } catch (Throwable) {
            return null;
        }

        $positional = 0;
        foreach ($call->arguments as $argument) {
            if ($argument->name !== null) {
                if (in_array(rtrim(trim($argument->name), characters: ' :'), $names, strict: true)) {
                    return $argument->index;
                }

                continue;
            }

            if ($argument->unpacked) {
                return null;
            }

            if ($positional === $position) {
                return $argument->index;
            }

            ++$positional;
        }

        return null;
    }

    /**
     * Whether a spread argument sits at or before the position, so the value
     * that fills the parameter cannot be known. `Invocation::getArgument()`
     * reads such a position as not passed.
     */
    public static function spreadBefore(Invocation $invocation, int $position): bool
    {
        foreach ($invocation->arguments as $index => $argument) {
            if ($index > $position) {
                return false;
            }

            if ($argument->unpacked) {
                return true;
            }
        }

        return false;
    }

    /**
     * The literal value of a boolean flag: its default when the call leaves
     * it out, null when it is computed or a spread could fill it.
     *
     * @param int $position The parameter's position, counting from zero.
     * @param non-empty-list<string> $names The parameter's name, without the
     *   dollar sign.
     */
    public static function literalBool(Invocation $invocation, int $position, bool $default, string ...$names): ?bool
    {
        if (self::spreadBefore($invocation, $position)) {
            return null;
        }

        $argument = $invocation->getArgument($position, ...$names);

        return $argument === null ? $default : $argument->type?->getLiteralBool();
    }
}
