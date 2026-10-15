<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use amateescu\MagoDrupal\Internal\FileMembers;
use Closure;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function preg_match;
use function preg_quote;

/**
 * Whether the type Mago has for an asserted value holds at the assertion.
 *
 * Mago keeps a property's narrowed type across calls that may change it: a
 * test sets `$this->fired = FALSE`, runs code whose callback sets it, and
 * asserts it. PHPStan forgets the narrowing after such a call. So a property
 * fetch counts only when the property's native type passes the assertion
 * too. A `@var` tag is not enforced, and an untyped property is null until
 * something is assigned, so neither counts, and a magic property, with no
 * declaration, never does. An `isset()`
 * tests `__isset()` or `ArrayAccess`, which types do not describe. So does an
 * offset read: Mago's `ArrayAccess` stub types `offsetGet()` without the null
 * an empty field item list returns for `$items[0]`. A `!` in front of any of
 * these is looked through, and a variable that a `static` or `global`
 * statement declares does not count either. Nor does one an inline `@var`
 * names: the tag is the test's own claim, often over a `load()` that can
 * return NULL, and the assertion is what checks it at runtime.
 *
 * @internal
 */
final class SettledValue
{
    /**
     * Nodes that only hold the expression below them.
     */
    private const WRAPPERS = [NodeKind::Expression, NodeKind::Access, NodeKind::Construct, NodeKind::Parenthesized];

    private function __construct() {}

    /**
     * @param Node $value The argument's value.
     * @param Closure(Type): bool $passes Whether a type passes the assertion.
     */
    public static function holds(NodeAnalysisContext $context, Node $value, Closure $passes): bool
    {
        $value = self::unwrap($context->source, $value);
        $node = self::withoutNegation($context->source, $value);

        // The declared type of a negated property doesn't say anything about
        // the negation, so it cannot vouch for it.
        return match ($node->kind) {
            NodeKind::IssetConstruct, NodeKind::StaticPropertyAccess, NodeKind::ArrayAccess => false,
            NodeKind::PropertyAccess, NodeKind::NullSafePropertyAccess => $node->id === $value->id
                && self::declared($context, $node, $passes),
            NodeKind::Variable => !self::shared($context->source, $node) && !self::documented($context, $node),
            default => true,
        };
    }

    /**
     * The operand of a `!`, so `!isset()` gets the same answer as `isset()`.
     */
    private static function withoutNegation(SourceFile $source, Node $node): Node
    {
        $children = $source->getChildren($node);
        if ($node->kind !== NodeKind::UnaryPrefix || count($children) !== 2 || $source->getText($children[0]) !== '!') {
            return $node;
        }

        return self::unwrap($source, $children[1]);
    }

    /**
     * Whether a `static` or `global` statement in the file declares the
     * variable. Its value then carries over from an earlier call or changes
     * elsewhere, which Mago does not follow. Any such statement in the file
     * counts, not only one in the function around the call.
     */
    private static function shared(SourceFile $source, Node $variable): bool
    {
        $name = preg_quote($source->getText($variable), delimiter: '/');
        $pattern = '/\b(?:static|global)\s+(?:\$\w+[^;,]*,\s*)*' . $name . '\b/';

        return preg_match($pattern, $source->contents) === 1;
    }

    /**
     * Whether the property's native type passes.
     *
     * Only `$this->name` is looked up, on the class around the call. The hook
     * gets the types of the call's arguments, not of the expressions inside
     * them, so any other receiver doesn't have a known class. In a trait the
     * class around the call is the trait, which rarely declares the property.
     *
     * @param Closure(Type): bool $passes
     */
    private static function declared(NodeAnalysisContext $context, Node $node, Closure $passes): bool
    {
        $source = $context->source;
        $children = $source->getChildren($node);
        $count = count($children);
        if ($count < 2 || $source->getText($children[0]) !== '$this') {
            return false;
        }

        // A dynamic name such as `$this->$name` is not a declared property.
        $name = $source->getText($children[$count - 1]);
        $class = FileMembers::of($source)->classAt($context->codebase, $source, $node->span);
        if ($class === null || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            return false;
        }

        // Mago names properties with their dollar sign.
        $declared = $context->codebase->getDeclaringProperty($class->name, '$' . $name)?->declaredType?->type;

        return $declared !== null && $passes($declared);
    }

    /**
     * Whether an inline `@var` tag in the method around the variable names
     * it. Names repeat across methods, so only that method counts, and the
     * whole file outside a class.
     */
    private static function documented(NodeAnalysisContext $context, Node $variable): bool
    {
        $source = $context->source;
        $members = FileMembers::of($source);
        $class = $members->classAt($context->codebase, $source, $variable->span);
        $method = $class === null ? null : $members->methodAt($context->codebase, $class, $variable->span);
        $text = $method?->location === null ? $source->contents : $source->getText($method->location->span);
        $name = preg_quote($source->getText($variable), delimiter: '/');

        return preg_match('/@var\s+[^\s$]+\s+' . $name . '\b/', $text) === 1;
    }

    private static function unwrap(SourceFile $source, Node $node): Node
    {
        while (in_array($node->kind, self::WRAPPERS, strict: true)) {
            $children = $source->getChildren($node);
            if (count($children) !== 1) {
                break;
            }

            $node = $children[0];
        }

        return $node;
    }
}
