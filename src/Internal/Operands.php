<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_filter;
use function array_map;
use function array_values;
use function implode;
use function in_array;
use function ltrim;
use function preg_replace;
use function strtolower;
use function trim;

/**
 * Structural checks on the operands of a ternary.
 *
 * One method per check, each a short walk over the kinds a PHP operand can
 * have. The branching follows the grammar.
 *
 * @internal
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class Operands
{
    /**
     * Node kinds of an operand that a fix can copy without changing behavior.
     */
    private const PURE_KINDS = [
        NodeKind::Expression,
        NodeKind::Parenthesized,
        NodeKind::Variable,
        NodeKind::DirectVariable,
        NodeKind::ArrayAccess,
        NodeKind::Access,
        NodeKind::PropertyAccess,
        NodeKind::NullSafePropertyAccess,
        NodeKind::StaticPropertyAccess,
        NodeKind::ClassConstantAccess,
        NodeKind::ClassLikeMemberSelector,
        NodeKind::ClassLikeConstantSelector,
        NodeKind::ConstantAccess,
        NodeKind::Identifier,
        NodeKind::LocalIdentifier,
        NodeKind::QualifiedIdentifier,
        NodeKind::FullyQualifiedIdentifier,
        NodeKind::Keyword,
        NodeKind::Literal,
        NodeKind::LiteralInteger,
        NodeKind::LiteralFloat,
        NodeKind::LiteralString,
    ];

    /**
     * Kinds that bind looser than `??`, so a branch of this kind needs
     * parentheses after the rewrite.
     */
    private const LOOSE_KINDS = [
        NodeKind::Conditional,
        NodeKind::Assignment,
        NodeKind::Yield,
        NodeKind::YieldFrom,
        NodeKind::Throw,
        NodeKind::Pipe,
        NodeKind::PrintConstruct,
        NodeKind::IncludeConstruct,
        NodeKind::IncludeOnceConstruct,
        NodeKind::RequireConstruct,
        NodeKind::RequireOnceConstruct,
    ];

    private const LOOSE_OPERATORS = ['and', 'or', 'xor', '|>'];

    /**
     * Kinds of a call whose result can stand as the compared operand.
     */
    private const CALL_KINDS = [
        NodeKind::FunctionCall,
        NodeKind::MethodCall,
        NodeKind::NullSafeMethodCall,
        NodeKind::StaticMethodCall,
    ];

    private function __construct() {}

    /**
     * Strips expression wrappers and parentheses.
     */
    public static function core(SourceFile $file, Node $node): Node
    {
        while ($node->kind === NodeKind::Expression || $node->kind === NodeKind::Parenthesized) {
            $next = self::expressions($file, $node)[0] ?? $file->getChildren($node)[0] ?? null;
            if ($next === null) {
                break;
            }

            $node = $next;
        }

        return $node;
    }

    /**
     * The children of a node that are expressions.
     *
     * @return list<Node>
     */
    public static function expressions(SourceFile $file, Node $node): array
    {
        return array_values(array_filter(
            $file->getChildren($node),
            static fn(Node $child): bool => $child->kind === NodeKind::Expression,
        ));
    }

    /**
     * A text key for the tree under a node. Spacing, comments, parentheses,
     * the quote style of plain strings and the case of keywords do not count.
     */
    public static function signature(SourceFile $file, Node $node): string
    {
        $core = self::core($file, $node);
        $children = $file->getChildren($core);
        if ($children === []) {
            return $core->kind->value . '|' . self::leafText($file, $core);
        }

        return (
            $core->kind->value
            . '('
            . implode(',', array_map(static fn(Node $child): string => self::signature($file, $child), $children))
            . ')'
        );
    }

    public static function leafText(SourceFile $file, Node $leaf): string
    {
        $text = $file->getText($leaf);
        if ($leaf->kind === NodeKind::Keyword) {
            return strtolower($text);
        }

        if ($leaf->kind === NodeKind::LiteralString) {
            return preg_replace('/^(["\'])([^\\\\"\'$]*)\1$/', replacement: '$2', subject: $text) ?? $text;
        }

        return $text;
    }

    public static function isNull(SourceFile $file, Node $node): bool
    {
        $core = self::core($file, $node);
        if ($core->kind !== NodeKind::Literal && $core->kind !== NodeKind::ConstantAccess) {
            return false;
        }

        return strtolower(ltrim($file->getText($core), characters: '\\')) === 'null';
    }

    /**
     * Whether the node is a variable, property, index, constant or call.
     */
    public static function isOperand(SourceFile $file, Node $core): bool
    {
        $children = $file->getChildren($core);
        $first = $children[0] ?? null;

        return match ($core->kind) {
            NodeKind::Variable => $first !== null && $first->kind === NodeKind::DirectVariable,
            NodeKind::ConstantAccess => !self::isNull($file, $core),
            NodeKind::ArrayAccess => $first !== null && self::isOperand($file, self::core($file, $first)),
            NodeKind::Access => $first !== null && self::isAccess($file, $first),
            NodeKind::Call => $first !== null && in_array($first->kind, self::CALL_KINDS, strict: true),
            default => false,
        };
    }

    public static function isAccess(SourceFile $file, Node $access): bool
    {
        if ($access->kind === NodeKind::StaticPropertyAccess || $access->kind === NodeKind::ClassConstantAccess) {
            return true;
        }

        $base = $file->getChildren($access)[0] ?? null;

        return (
            ($access->kind === NodeKind::PropertyAccess || $access->kind === NodeKind::NullSafePropertyAccess)
            && $base !== null
            && self::isOperand($file, self::core($file, $base))
        );
    }

    /**
     * Whether every node below is a plain read with no side effects.
     */
    public static function isPure(SourceFile $file, Node $node): bool
    {
        if (!in_array($node->kind, self::PURE_KINDS, strict: true)) {
            return false;
        }

        foreach ($file->getChildren($node) as $child) {
            if (!self::isPure($file, $child)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether B can follow `??` without parentheses.
     */
    public static function bindsTighter(SourceFile $file, Node $branch): bool
    {
        // Parentheses around B already keep it together.
        $core = $file->getChildren($branch)[0] ?? $branch;
        if ($core->kind === NodeKind::Parenthesized) {
            return true;
        }

        if ($core->kind === NodeKind::Construct) {
            $core = $file->getChildren($core)[0] ?? $core;
        }

        if (in_array($core->kind, self::LOOSE_KINDS, strict: true)) {
            return false;
        }

        if ($core->kind !== NodeKind::Binary) {
            return true;
        }

        foreach ($file->getChildren($core) as $child) {
            if (
                $child->kind === NodeKind::BinaryOperator
                && in_array(strtolower(trim($file->getText($child))), self::LOOSE_OPERATORS, strict: true)
            ) {
                return false;
            }
        }

        return true;
    }
}
