<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function explode;
use function in_array;
use function preg_match;
use function str_contains;
use function strcspn;
use function strlen;
use function strpbrk;
use function strrpos;
use function strspn;
use function substr;

/**
 * Reports a docblock type written as the short name of an imported class.
 *
 * Ports Drupal.Commenting.DataTypeNamespace. The rule only handles single,
 * unaliased imports. The ported sniff itself reads only those reliably. The
 * rule skips a grouped or comma-separated `use` statement.
 *
 * The rule targets the whole file, not one `use` statement at a time. It
 * thus reads the imports once and walks every docblock once. A per-import
 * dispatch walks every docblock once per import. This has one
 * simplification: an import counts as in scope for the whole file, not only
 * for the docblocks below it. That includes a docblock above the imports,
 * most often a procedural file's own `@file` block. It does not include the
 * unusual case of a `use` statement after code that already refers to the
 * class.
 *
 * The fix writes the fully qualified name in place of each imported short
 * name of the tag's type. It is left out for a type that does not start on
 * the tag's first line, for a docblock above the import, and for a file with
 * more than one namespace, whose imports belong to their own block.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocTypeNamespaceRule implements Rule
{
    private const TAGS = ['param', 'return', 'var', 'throws'];

    public function getDefinition(): RuleDefinition
    {
        // `Use` is a target. Rust then collects every import into the file's
        // target-node list, and the Program pass reads that list at no cost.
        // The per-import dispatches do nothing. A node table query is one
        // full, unindexed re-scan per file.
        return new RuleDefinition(
            code: 'drupal/doc-type-namespace',
            name: 'Doc comment type namespace',
            description: 'Reports @param, @return, @var and @throws types written as an imported short name instead of the fully qualified name.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Use, NodeKind::Namespace],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $imports = $this->singleImports($context);
        if ($imports === []) {
            return;
        }

        $namespaces = 0;
        foreach ($context->file->getTargetNodes() as $node) {
            $namespaces += $node->kind === NodeKind::Namespace ? 1 : 0;
        }

        $fixable = $namespaces <= 1;

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            foreach (Docblocks::tags($context->file, $trivia->span) as $tag) {
                if (!in_array($tag->name, self::TAGS, strict: true)) {
                    continue;
                }

                $this->checkTag($context, $tag, $imports, $fixable);
            }
        }
    }

    /**
     * Returns every single import in the file, keyed by the short name that
     * it introduces, with the offset where its statement ends.
     *
     * @return array<string, array{string, int}>
     */
    private function singleImports(LintContext $context): array
    {
        $imports = [];
        foreach ($context->file->getTargetNodes() as $use) {
            if ($use->kind !== NodeKind::Use) {
                continue;
            }

            $import = $this->singleImport($context->file->getText($use));
            if ($import === null) {
                continue;
            }

            [$fullyQualified, $shortName] = $import;
            $imports[$shortName] = [$fullyQualified, $use->span->end];
        }

        return $imports;
    }

    /**
     * @return ?array{string, string}
     */
    private function singleImport(string $text): ?array
    {
        if (
            str_contains($text, ',')
            || str_contains($text, '{')
            || preg_match('/^use\s+(function|const)\s/', $text) === 1
        ) {
            return null;
        }

        $matches = [];
        if (
            preg_match(
                '/^use\s+\\\\?([A-Za-z_][A-Za-z0-9_\\\\]*)(?:\s+as\s+([A-Za-z_][A-Za-z0-9_]*))?\s*;/s',
                $text,
                $matches,
            ) !== 1
        ) {
            return null;
        }

        $fullyQualified = $matches[1];
        $separator = strrpos($fullyQualified, needle: '\\');
        $shortName = $matches[2] ?? substr($fullyQualified, $separator === false ? 0 : $separator + 1);

        return [$fullyQualified, $shortName];
    }

    /**
     * Reports the tag once when its type names an imported short name, with
     * an edit for each such name when the fix applies.
     *
     * @param array<string, array{string, int}> $imports
     * @param bool $fixable Whether the file allows the fix at all.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function checkTag(LintContext $context, DocblockTag $tag, array $imports, bool $fixable): void
    {
        [$type] = Docblocks::splitType($tag->content());
        if ($type === null) {
            return;
        }

        // The edits need the type's offset, which is known when the type
        // starts the tag's first line.
        $line = $tag->lines[0];
        $indent = strspn($line->text, characters: " \t");
        $start = $fixable && substr($line->text, $indent, strlen($type)) === $type ? $line->offset + $indent : null;

        $first = null;
        $edits = [];
        $position = 0;
        // Most types are plain, and a plain type is one member.
        $members = strpbrk($type, characters: '|<[?') === false ? [$type] : explode('|', $type);
        foreach ($members as $segment) {
            $nullable = strspn($segment, characters: '?');
            $member = substr($segment, $nullable, strcspn($segment, characters: '<[', offset: $nullable));
            $import = $imports[$member] ?? null;
            if ($import !== null) {
                [$fullyQualified, $useEnd] = $import;
                $first ??= [$member, $fullyQualified];
                if ($start !== null && $tag->nameSpan->start >= $useEnd) {
                    $offset = $start + $position + $nullable;
                    $edits[] = TextEdit::replace(new Span($offset, $offset + strlen($member)), '\\' . $fullyQualified);
                }
            }

            $position += strlen($segment) + 1;
        }

        if ($first === null) {
            return;
        }

        [$member, $fullyQualified] = $first;
        $issue = Issue::new(
            "The @{$tag->name} type must be fully qualified. Use \\{$fullyQualified} instead of {$member}.",
            $tag->contentSpan(),
        );
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        $context->report($issue);
    }
}
