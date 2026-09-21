<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function ltrim;
use function max;
use function str_contains;
use function str_ends_with;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Reports a namespaced class written out in full instead of imported.
 *
 * Ports Drupal.Classes.FullyQualifiedNamespace. The rule skips a name with
 * no namespace of its own, such as `\Exception`. Drupal writes those at the
 * call site. The `drupal/redundant-use` rule enforces that from the other
 * side.
 */
final class FullyQualifiedNameRule implements Rule
{
    /**
     * Kinds that wrap a name and do not change what it refers to.
     */
    private const WRAPPERS = [NodeKind::Identifier, NodeKind::Expression, NodeKind::ConstantAccess];

    /**
     * Kinds that write a name in full by design.
     */
    private const EXEMPT = [NodeKind::Use, NodeKind::TraitUse, NodeKind::AttributeList, NodeKind::Namespace];

    /**
     * Exempt kinds that are also targets. The Program pass can then skip
     * every name inside them by span, with no walk up from each name. Almost
     * every namespaced name in a clean file is in a `use` statement, so most
     * of the walks are for those. `Namespace` is not in the list, because
     * its span covers the whole file.
     */
    private const SKIPPED = [NodeKind::Use, NodeKind::TraitUse, NodeKind::AttributeList];

    /**
     * Kinds that end the walk. The name is then in code, not in a declaration.
     */
    private const CODE = [NodeKind::NamespaceBody, NodeKind::NamespaceImplicitBody, NodeKind::Program];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/fully-qualified-name',
            name: 'Fully qualified name',
            description: 'Reports namespaced classes referenced in full instead of through a use statement.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // The name kinds and the skipped kinds are targets. Rust then
            // collects them into the file's target list, and the Program
            // pass reads that list instead of the node table. Their own
            // dispatches do nothing.
            targets: [
                NodeKind::Program,
                NodeKind::FullyQualifiedIdentifier,
                NodeKind::QualifiedIdentifier,
                ...self::SKIPPED,
            ],
        );
    }

    public function lint(LintContext $context): void
    {
        // The name dispatches do nothing. The Program pass reads them all.
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        // An API file documents the names that it mentions, so it writes
        // them in full.
        if (str_ends_with(strtolower($context->file->path), '.api.php')) {
            return;
        }

        // The target list is in source order, and lists a node before its
        // descendants. A skipped construct thus sets the end offset of its
        // names before any of them come up.
        $skipUntil = 0;
        foreach ($context->file->getTargetNodes() as $name) {
            if (in_array($name->kind, self::SKIPPED, strict: true)) {
                $skipUntil = max($skipUntil, $name->span->end);

                continue;
            }

            if ($name->kind !== NodeKind::FullyQualifiedIdentifier && $name->kind !== NodeKind::QualifiedIdentifier) {
                continue;
            }

            if ($name->span->start >= $skipUntil) {
                $this->check($context, $name);
            }
        }
    }

    /**
     * Checks one written name.
     */
    private function check(LintContext $context, Node $name): void
    {
        $written = ltrim(trim($context->file->getText($name)), characters: '\\');
        if (!str_contains($written, '\\') || $this->isExempt($context->file, $name)) {
            return;
        }

        $separator = strrpos($written, needle: '\\');
        $short = $separator === false ? $written : substr($written, $separator + 1);

        $context->report(Issue::new("Do not write {$written} in full. Import it.", $name->span)->withHelp(
            "Add a use statement for it and write {$short} here.",
        ));
    }

    /**
     * Whether a name is in a construct that writes names in full.
     */
    private function isExempt(SourceFile $file, Node $name): bool
    {
        // A callee keeps its namespace, because PHP falls back to the global
        // function. The ported sniff permits that too.
        $callee = true;
        $parent = $file->getParent($name);
        while ($parent !== null) {
            if (in_array($parent->kind, self::EXEMPT, strict: true)) {
                return true;
            }

            if (in_array($parent->kind, self::CODE, strict: true)) {
                return false;
            }

            if ($parent->kind === NodeKind::FunctionCall) {
                return $callee;
            }

            $callee = $callee && in_array($parent->kind, self::WRAPPERS, strict: true);
            $parent = $file->getParent($parent);
        }

        return false;
    }
}
