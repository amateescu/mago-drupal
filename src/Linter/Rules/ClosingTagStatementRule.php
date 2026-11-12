<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function rtrim;
use function str_ends_with;
use function strlen;
use function substr;
use function trim;

/**
 * Reports a `?>` at the end of a file that ends the last statement.
 *
 * Ports the part of Zend.Files.ClosingTag that Mago leaves out. Mago's
 * `no-closing-tag` and the formatter remove a closing tag after a complete
 * statement. A tag that stands in for the `;`, as in `$a = 1 ?>`, is the
 * statement's terminator, and both keep it.
 *
 * @internal
 */
final class ClosingTagStatementRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/closing-tag-statement',
            name: 'Closing tag statement',
            description: 'Reports a closing tag at the end of a file that ends a statement with no semicolon.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // Program has the parent of the tag in the snapshot.
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // Coder's ruleset skips a template.
        $code = rtrim($file->contents);
        if (!str_ends_with($code, '?>') || str_ends_with($file->path, '.tpl.php')) {
            return;
        }

        foreach ($file->getNodes(NodeKind::ClosingTag) as $tag) {
            $terminator = $tag->span->end === strlen($code) ? $file->getParent($tag) : null;
            if ($terminator === null || $terminator->kind !== NodeKind::Terminator) {
                continue;
            }

            $issue = Issue::new('End the last statement with a semicolon and remove the closing tag.', $tag->span);
            $edit = self::edit($file, $terminator, $tag);
            $context->report($edit === null ? $issue : $issue->withEdit($edit));
        }
    }

    /**
     * Replaces the tag and what is around it with a semicolon and a line
     * break. A comment between the statement and the tag would swallow the
     * semicolon or end up after it, so that case doesn't get a fix.
     */
    private static function edit(SourceFile $file, Node $terminator, Node $tag): ?TextEdit
    {
        $statement = $file->getParent($terminator);
        $previous = null;
        foreach ($statement === null ? [] : $file->getChildren($statement) as $part) {
            if ($part->id === $terminator->id) {
                break;
            }

            $previous = $part;
        }

        if ($previous === null) {
            return null;
        }

        $start = $previous->span->end;
        if (trim(substr($file->contents, $start, $tag->span->start - $start)) !== '') {
            return null;
        }

        return TextEdit::replace(new Span($start, strlen($file->contents)), ";\n");
    }
}
