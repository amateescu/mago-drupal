<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;

/**
 * One call or instantiation, reduced to a name and its arguments.
 *
 * Drupal reaches the same behaviour through `t()` and through
 * `new TranslatableMarkup()`, so rules covering both need one view over the
 * two node shapes.
 *
 * The name resolves up front and the arguments only on first use. Rules
 * filter on the name, and almost every candidate fails that filter, so an
 * eager argument walk would be wasted work for all of them.
 *
 * @internal
 */
final class Invocation
{
    /** @var null|list<Node> */
    private ?array $arguments = null;

    private function __construct(
        private readonly SourceFile $file,
        private readonly Node $node,
        public readonly string $name,
    ) {}

    /**
     * Builds a view over a call or instantiation node.
     *
     * Returns NULL for any other node, and for a callee Mago cannot name.
     */
    public static function fromNode(SourceFile $file, Node $node): ?self
    {
        $name = match (true) {
            $node->kind === NodeKind::Instantiation => Instantiations::name($file, $node),
            self::isCall($node) => Calls::name($file, $node),
            default => null,
        };

        return $name === null ? null : new self($file, $node, $name);
    }

    /**
     * Returns the positional argument values, in source order.
     *
     * @return list<Node>
     */
    public function arguments(): array
    {
        return $this->arguments ??= $this->node->kind === NodeKind::Instantiation
            ? Instantiations::arguments($this->file, $this->node)
            : Calls::positionalArguments($this->file, CallExpression::fromNode($this->file, $this->node));
    }

    /**
     * Returns the argument at a position, or NULL when it is absent or named.
     */
    public function argument(int $position): ?Node
    {
        return $this->arguments()[$position] ?? null;
    }

    /**
     * Whether the call passes no arguments at all.
     *
     * Named and unpacked arguments count as present here, unlike arguments(),
     * which lists only positional ones.
     */
    public function isEmpty(): bool
    {
        foreach ($this->file->getChildren($this->node) as $child) {
            if ($child->kind === NodeKind::ArgumentList) {
                return $this->file->getChildren($child) === [];
            }
        }

        return true;
    }

    private static function isCall(Node $node): bool
    {
        return in_array($node->kind, Calls::CALL_KINDS, strict: true);
    }
}
