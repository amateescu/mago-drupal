<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function str_starts_with;
use function strtolower;
use function substr;

/**
 * Finds and matches call expressions by name.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class Calls
{
    /**
     * Node kinds that call a named function or method.
     */
    public const CALL_KINDS = [
        NodeKind::FunctionCall,
        NodeKind::MethodCall,
        NodeKind::NullSafeMethodCall,
        NodeKind::StaticMethodCall,
    ];

    private const IDENTIFIER_KINDS = [
        NodeKind::Identifier,
        NodeKind::LocalIdentifier,
        NodeKind::QualifiedIdentifier,
        NodeKind::FullyQualifiedIdentifier,
    ];

    private function __construct() {}

    /**
     * Returns the first call to any of the named functions inside a subtree.
     *
     * Matches `t()` and `$this->t()` alike, which is how Drupal's sniffs treat
     * translation calls.
     *
     * @param list<string> $names
     */
    public static function findFirst(SourceFile $file, Node $node, array $names): ?Node
    {
        $wanted = self::normalizeAll($names);

        // One depth-first walk in source order, stopping at the first match.
        $stack = [$node];
        while (($current = array_pop($stack)) !== null) {
            if (
                $current !== $node
                && in_array($current->kind, self::CALL_KINDS, strict: true)
                && self::isWanted($file, $current, $wanted)
            ) {
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
     * Finds plain function calls to any of the named functions in a subtree.
     *
     * Method calls are excluded. A rule about procedural functions must not
     * match `$this->t()`, which reports the same callee name as `t()`.
     *
     * @param list<string> $names
     * @return array<string, list<Node>> Matched calls grouped by normalized name.
     */
    public static function findFunctions(SourceFile $file, Node $node, array $names): array
    {
        $wanted = self::normalizeAll($names);

        $found = [];
        foreach ($file->getDescendants($node, NodeKind::FunctionCall) as $candidate) {
            $name = self::candidateName($file, $candidate);
            if ($name === null) {
                continue;
            }

            $name = self::normalize($name);
            if ($wanted[$name] ?? false) {
                $found[$name][] = $candidate;
            }
        }

        return $found;
    }

    /**
     * Returns the name to match for a candidate call node.
     *
     * An imported resolution is trusted the way CallRule trusts it, so
     * `use function Foo\bar;` does not match global bar() and an aliased
     * import still does. Only plain function calls carry a resolved name at
     * the call's span; method selectors use the written text.
     */
    private static function candidateName(SourceFile $file, Node $node): ?string
    {
        if ($node->kind === NodeKind::FunctionCall) {
            $resolved = $file->getResolvedName($node);
            if ($resolved !== null && $resolved->imported) {
                return $resolved->name;
            }
        }

        return self::name($file, $node);
    }

    /**
     * Returns the callee name of a call node, as written in the source.
     *
     * CallExpression::fromNode materializes every argument up front, so name
     * lookups that only filter candidates read the callee children directly.
     */
    public static function name(SourceFile $file, Node $node): ?string
    {
        $children = $file->getChildren($node);

        if ($node->kind === NodeKind::FunctionCall) {
            $callee = $children[0] ?? null;
            while (
                $callee !== null
                && ($callee->kind === NodeKind::Expression || $callee->kind === NodeKind::ConstantAccess)
            ) {
                $callee = $file->getChildren($callee)[0] ?? null;
            }

            if ($callee !== null && in_array($callee->kind, self::IDENTIFIER_KINDS, strict: true)) {
                return $file->getText($callee);
            }

            return null;
        }

        // Method and static calls keep the selector in the member child.
        $member = $children[1] ?? null;
        $selector = $member === null ? null : $file->getChildren($member)[0] ?? null;

        return $selector !== null && $selector->kind === NodeKind::LocalIdentifier ? $file->getText($selector) : null;
    }

    /**
     * Returns the positional argument values of a call view, in source order.
     *
     * Named and unpacked arguments are skipped, so an index here lines up with
     * the parameter position only when every earlier argument is positional.
     *
     * @return list<Node>
     */
    public static function positionalArguments(SourceFile $file, CallExpression $call): array
    {
        $values = [];
        foreach ($call->arguments as $argument) {
            if ($argument->unpacked || $argument->name !== null) {
                continue;
            }

            $values[] = Values::unwrap($file, $argument->value);
        }

        return $values;
    }

    /**
     * Whether a callee name matches a wanted name.
     */
    public static function matches(string $name, string $wanted): bool
    {
        return self::normalize($name) === self::normalize($wanted);
    }

    /**
     * Normalizes wanted names into a lookup set.
     *
     * Finders test one candidate per descendant, so the list is normalized
     * once here instead of once per candidate.
     *
     * @param list<string> $names
     * @return array<string, true>
     */
    public static function normalizeAll(array $names): array
    {
        $set = [];
        foreach ($names as $name) {
            $set[self::normalize($name)] = true;
        }

        return $set;
    }

    /**
     * Lowercases a name and drops any leading separator.
     *
     * Callers keying a table by name have to normalize the same way matching
     * does, or `\format_date()` matches and then misses the lookup.
     */
    public static function normalize(string $name): string
    {
        if (str_starts_with($name, '\\')) {
            $name = substr($name, offset: 1);
        }

        return strtolower($name);
    }

    /**
     * Whether a call node's name is in a normalized wanted set.
     *
     * @param array<string, true> $wanted
     */
    private static function isWanted(SourceFile $file, Node $node, array $wanted): bool
    {
        $name = self::candidateName($file, $node);

        return $name !== null && ($wanted[self::normalize($name)] ?? false);
    }
}
