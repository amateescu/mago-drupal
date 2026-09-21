<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function preg_match;
use function strtolower;
use function trim;

/**
 * Reports a method declared without a visibility keyword.
 *
 * Ports Drupal.Scope.MethodScope and Drupal.Methods.MethodDeclaration. PHP
 * makes such a method public. Drupal wants that written down.
 */
final class MethodVisibilityRule implements Rule
{
    private const VISIBILITIES = ['public', 'protected', 'private'];

    /**
     * A method head that declares its visibility. It has the attributes,
     * then any abstract, final or static keyword, then the visibility
     * keyword. The rule matches it in place at the method's offset.
     */
    private const DECLARED = '/\G(?:#\[[^\]]*\]\s*)*(?:(?:abstract|final|static)\s+)*(?:public|protected|private)\b/i';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/method-visibility',
            name: 'Method visibility',
            description: 'Reports methods declared without an explicit visibility keyword.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // Nearly every method declares its visibility. The text says so
        // without reading the method's nodes. A head that the pattern
        // cannot read, such as an attribute with a `]` in it, goes on to the
        // node walk. Nothing is missed.
        if (preg_match(self::DECLARED, $file->contents, offset: $context->node->span->start) === 1) {
            return;
        }

        $anchor = null;
        foreach ($file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::Modifier && $this->isVisibility($file, $child)) {
                return;
            }

            // The fix goes in front of `static` when there is one, because
            // Drupal writes the visibility before it. Otherwise it goes in
            // front of `function`. Both places keep an abstract or final
            // keyword before the visibility.
            if ($child->kind === NodeKind::Modifier && strtolower(trim($file->getText($child))) === 'static') {
                $anchor ??= $child;
            }

            if ($anchor === null && $child->kind === NodeKind::Keyword) {
                $anchor = $child;
            }
        }

        $identifier = Nodes::declaredIdentifier($file, $context->node);
        if ($identifier === null) {
            return;
        }

        $name = $file->getText($identifier);
        $issue = Issue::new("Declare the visibility of the method {$name}().", $identifier->span);

        if ($anchor !== null) {
            $issue = $issue->withEdit(TextEdit::insert($anchor->span->start, text: 'public '));
        }

        $context->report($issue);
    }

    /**
     * Whether a modifier keyword declares visibility.
     */
    private function isVisibility(SourceFile $file, Node $modifier): bool
    {
        return in_array(strtolower(trim($file->getText($modifier))), self::VISIBILITIES, strict: true);
    }
}
