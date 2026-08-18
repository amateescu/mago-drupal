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
 * Ports Drupal.Semantics.RemoteAddress. Drupal sits behind reverse proxies, so
 * the raw superglobal is the wrong client address.
 *
 * Program is a target only so every access carries its parent chain in the
 * snapshot, whatever other rules are active; the write detection below reads
 * that chain and lint() ignores the Program node itself. Telling every write
 * shape apart from a read needs its branches, so the complexity is deliberate.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class RemoteAddressRule implements Rule
{
    /**
     * Kinds that may sit between an access and the assignment writing it.
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
            description: "Reports \$_SERVER['REMOTE_ADDR'] reads, which ignore Drupal's reverse-proxy settings.",
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

        // Almost no array access touches the superglobal, and scanning the
        // source is far cheaper than materializing and unwrapping children.
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

        // Writing the superglobal is how tests and the installer seed a
        // request, so only reads are reported.
        if ($this->isWrite($file, $context->node)) {
            return;
        }

        $context->report(Issue::new(
            "Use the 'request_stack' service instead of \$_SERVER['REMOTE_ADDR'].",
            $context->node->span,
        )->withHelp("\\Drupal::request()->getClientIp() honours the reverse-proxy settings."));
    }

    /**
     * Whether the access is written rather than read.
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

            // A destructuring key is evaluated, so it is a read even though
            // the element sits in an assignment target. The key comes first.
            if (
                $parent->kind === NodeKind::KeyValueArrayElement
                && ($file->getChildren($parent)[0] ?? null)?->id === $child->id
            ) {
                return false;
            }

            // A subscript is evaluated too; only the base of a deeper access
            // stays on the write path. The base comes first.
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
