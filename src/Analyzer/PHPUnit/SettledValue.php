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

/**
 * Whether the type Mago has for an asserted value holds at the assertion.
 *
 * Mago keeps a property's narrowed type across calls that may change it: a
 * test sets `$this->fired = FALSE`, runs code whose callback sets it, and
 * asserts it. PHPStan forgets the narrowing after such a call. So a property
 * fetch counts only when the property's declared type passes the assertion
 * too, and a magic property, with no declaration, never does. An `isset()`
 * tests `__isset()` or `ArrayAccess`, which types do not describe. So does an
 * offset read: Mago's `ArrayAccess` stub types `offsetGet()` without the null
 * an empty field item list returns for `$items[0]`.
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
        $node = self::unwrap($context->source, $value);

        return match ($node->kind) {
            NodeKind::IssetConstruct, NodeKind::StaticPropertyAccess, NodeKind::ArrayAccess => false,
            NodeKind::PropertyAccess, NodeKind::NullSafePropertyAccess => self::declared($context, $node, $passes),
            default => true,
        };
    }

    /**
     * Whether the property's declared type passes.
     *
     * Only `$this->name` is looked up, on the class around the call. The
     * hook gets the types of the call's arguments, not of the expressions
     * inside them, so any other receiver has no known class. In a trait the
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
        $declared = $context->codebase->getDeclaringProperty($class->name, '$' . $name)?->type?->type;

        return $declared !== null && $passes($declared);
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
