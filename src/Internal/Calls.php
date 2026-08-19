<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function chr;
use function count;
use function in_array;
use function str_starts_with;
use function strspn;
use function strtolower;
use function substr;

/**
 * Finds and matches call expressions by name.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
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
                && self::matchWanted($file, $current, $wanted) !== null
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
            $name = self::matchWanted($file, $candidate, $wanted);
            if ($name !== null) {
                $found[$name][] = $candidate;
            }
        }

        return $found;
    }

    /**
     * Returns the normalized wanted name a call node matches, or NULL.
     *
     * An imported resolution is trusted over the written name, so
     * `use function Foo\bar;` does not match global bar() and an aliased
     * import still does. Mago resolves an unimported unqualified call into
     * the current namespace, but PHP falls back to the global function at
     * runtime, so only an imported resolution beats the written text. That
     * is how Mago's own rules match global functions too.
     *
     * The written name comes from the cheap text scan, which over-matches
     * one shape: a curried call like `md5(1)(2)` starts with `md5` in the
     * source even though its callee is another call. Every hit from the
     * scan is re-checked against name(), which walks the real callee, so
     * a match reported here is always exact. Misses need no re-check: the
     * scanned name covers every name the walk would find.
     *
     * Memoized per node: several call rules subscribe to the same call
     * kinds, so the worker dispatches every one of them for the same node
     * and each would re-derive the same name. One slot per file, cleared
     * when the path changes, the same pattern `DrupalFile::fromSource()`
     * and `Docblocks::lines()` already use.
     *
     * @param array<string, true> $wanted
     */
    public static function matchWanted(SourceFile $file, Node $node, array $wanted): ?string
    {
        static $path = '';
        /** @var array<int, string|null> $names */
        static $names = [];
        /** @var array<int, true> $resolved */
        static $resolved = [];

        if ($path !== $file->path) {
            $path = $file->path;
            $names = [];
            $resolved = [];
        }

        $id = $node->id;
        if (!array_key_exists($id, $names)) {
            $name = null;
            if ($node->kind === NodeKind::FunctionCall) {
                $imported = $file->getResolvedName($node);
                if ($imported !== null && $imported->imported) {
                    $name = $imported->name;
                    $resolved[$id] = true;
                }
            }

            $names[$id] = $name ?? self::writtenNameFast($file, $node);
        }

        $name = $names[$id];
        if ($name === null) {
            return null;
        }

        $name = self::normalize($name);
        if (!($wanted[$name] ?? false)) {
            return null;
        }

        if ($resolved[$id] ?? false) {
            return $name;
        }

        $precise = self::name($file, $node);

        return $precise !== null && self::normalize($precise) === $name ? $name : null;
    }

    /**
     * Returns a call node's written name without walking its callee.
     *
     * A function call's callee text sits at the node's own span start, so
     * the identifier run there is the written name. It over-matches curried
     * calls, whose callee is another call starting with the same run, so a
     * caller acting on a hit has to re-check it against name().
     *
     * A method or static call keeps the selector in the member child, and
     * a named selector shares the member's span exactly, so the member's
     * text is the selector. Variable and expression selectors show as `$`
     * or `{` in the first byte, the shapes name() maps to NULL, which
     * makes this branch exact rather than over-matching.
     */
    public static function writtenNameFast(SourceFile $file, Node $node): ?string
    {
        if ($node->kind === NodeKind::FunctionCall) {
            return self::leadingIdentifier($file->contents, $node->span->start);
        }

        $member = $file->getChildren($node)[1] ?? null;
        if ($member === null) {
            return null;
        }

        $text = $file->getText($member);
        if ($text === '' || $text[0] === '$' || $text[0] === '{') {
            return null;
        }

        return $text;
    }

    /**
     * Reads the identifier run at a byte offset straight from the source.
     *
     * The accepted bytes cover PHP's identifier grammar plus the namespace
     * separator, so a qualified name comes back whole and a dynamic callee
     * yields NULL at its `$`, `(` or quote.
     */
    public static function leadingIdentifier(string $contents, int $offset): ?string
    {
        $length = strspn($contents, self::identifierBytes(), $offset);

        return $length === 0 ? null : substr($contents, $offset, $length);
    }

    private static function identifierBytes(): string
    {
        static $bytes = '';
        if ($bytes === '') {
            $bytes = '\\_0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            for ($byte = 0x80; $byte <= 0xff; ++$byte) {
                $bytes .= chr($byte);
            }
        }

        return $bytes;
    }

    /**
     * Finds plain function calls to any of the named functions among the
     * file's pre-collected target nodes.
     *
     * Rust gathers every node whose kind any active rule targets into the
     * snapshot's target list, already materialized before dispatch, so
     * reading calls from it costs one array pass instead of a full tree
     * walk in PHP. The calling rule must declare `NodeKind::FunctionCall`
     * among its own targets, or the guarantee only holds while some other
     * rule that declares it happens to be active.
     *
     * @param list<string> $names
     * @return array<string, list<Node>> Matched calls grouped by normalized name.
     */
    public static function findFunctionsInTargets(SourceFile $file, ?Node $within, array $names): array
    {
        $wanted = self::normalizeAll($names);

        $found = [];
        foreach ($file->getTargetNodes() as $candidate) {
            if ($candidate->kind !== NodeKind::FunctionCall) {
                continue;
            }

            if ($within !== null && !$within->span->contains($candidate->span)) {
                continue;
            }

            $name = self::matchWanted($file, $candidate, $wanted);
            if ($name !== null) {
                $found[$name][] = $candidate;
            }
        }

        return $found;
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
}
