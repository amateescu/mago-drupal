<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function str_contains;

/**
 * Reports reads of $_SERVER['REMOTE_ADDR'].
 *
 * Ports Drupal.Semantics.RemoteAddress. Drupal runs behind reverse proxies, so
 * the raw superglobal is the wrong client address.
 *
 * Program is a target for one reason: every access then has its parent chain
 * in the snapshot, whatever other rules are active. The write detection below
 * reads that chain. lint() ignores the Program node itself. The rule must tell
 * every write shape apart from a read, so it needs the branches. The
 * complexity is deliberate.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class RemoteAddressRule implements Rule
{
    /**
     * Kinds that can be between an access and the assignment that writes it.
     */
    private const WRAPPER_KINDS = [
        NodeKind::Expression,
        NodeKind::Parenthesized,
        NodeKind::Array,
        NodeKind::LegacyArray,
        NodeKind::List,
        NodeKind::ArrayElement,
        NodeKind::ValueArrayElement,
        NodeKind::KeyValueArrayElement,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/remote-address',
            name: 'Raw remote address',
            description: "Reports a read of \$_SERVER['REMOTE_ADDR']. The read ignores Drupal's reverse-proxy settings.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::ArrayAccess, NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::ArrayAccess) {
            return;
        }

        $file = $context->file;

        // Almost no array access touches the superglobal. A text search of
        // the source is much cheaper than reading and unwrapping the children.
        if (!str_contains($file->getText($context->node), 'REMOTE_ADDR')) {
            return;
        }

        $children = $file->getChildren($context->node);

        $target = Values::unwrap($file, $children[0] ?? $context->node);
        if ($target->kind === NodeKind::Variable) {
            $target = Values::unwrap($file, $file->getChildren($target)[0] ?? $target);
        }

        if ($target->kind !== NodeKind::DirectVariable || $file->getText($target) !== '$_SERVER') {
            return;
        }

        $key = Values::unwrap($file, $children[1] ?? $context->node);
        if (Values::literalString($file, $key) !== 'REMOTE_ADDR') {
            return;
        }

        // Tests and the installer write the superglobal to seed a request.
        // The rule reports only reads.
        if ($this->isWrite($file, $context->node)) {
            return;
        }

        $context->report(Issue::new(
            "Use the 'request_stack' service instead of \$_SERVER['REMOTE_ADDR'].",
            $context->node->span,
        )->withHelp("\\Drupal::request()->getClientIp() obeys the reverse-proxy settings."));
    }

    /**
     * Whether the access is a write and not a read.
     *
     * One walk up the parent chain covers plain and compound assignments,
     * every unset() argument, and destructuring patterns.
     *
     * @mago-expect lint:halstead
     */
    private function isWrite(SourceFile $file, Node $node): bool
    {
        $child = $node;
        $parent = $file->getParent($child);

        while ($parent !== null) {
            if ($parent->kind === NodeKind::Assignment) {
                return ($file->getChildren($parent)[0] ?? null)?->id === $child->id;
            }

            if ($parent->kind === NodeKind::Unset) {
                return true;
            }

            // PHP evaluates a destructuring key, so it is a read even when
            // the element is in an assignment target. The key is the first
            // child.
            if (
                $parent->kind === NodeKind::KeyValueArrayElement
                && ($file->getChildren($parent)[0] ?? null)?->id === $child->id
            ) {
                return false;
            }

            // PHP evaluates a subscript too. Only the base of a deeper access
            // stays on the write path. The base is the first child.
            if (
                $parent->kind === NodeKind::ArrayAccess
                && ($file->getChildren($parent)[0] ?? null)?->id !== $child->id
            ) {
                return false;
            }

            if (
                $parent->kind !== NodeKind::ArrayAccess
                && !in_array($parent->kind, self::WRAPPER_KINDS, strict: true)
            ) {
                return false;
            }

            $child = $parent;
            $parent = $file->getParent($parent);
        }

        return false;
    }
}
