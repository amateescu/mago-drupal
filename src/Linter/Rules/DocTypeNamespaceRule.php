<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\ImportPlan;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function array_key_exists;
use function array_keys;
use function explode;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_quote;
use function str_contains;
use function strcspn;
use function strlen;
use function strpbrk;
use function strrpos;
use function strspn;
use function strtolower;
use function substr;

/**
 * Reports a docblock type written as the short name of a class that is
 * imported only for docblocks.
 *
 * Coder 9 and core 11.5 accept short names in docblocks: they dropped
 * Drupal.Commenting.DataTypeNamespace. But their
 * SlevomatCodingStandard.Namespaces.UnusedUses does not read docblocks, so
 * an import that only docblocks use is unused there, and phpcbf deletes it
 * and leaves the docblock naming a class that no longer resolves. This rule
 * reports the docblock type instead, and its fix writes the full name and
 * deletes the import, which keeps the docblock right. A short name whose
 * import the code also uses is fine. The rule only handles single imports,
 * and skips a grouped or comma-separated `use` statement.
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
 * more than one namespace, whose imports belong to their own block. The
 * import goes only when every mention of its name in the file's docblocks
 * is rewritten.
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
            description: 'Reports @param, @return, @var and @throws types written as the short name of a class imported only for docblocks.',
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

        $imports = $this->docblockOnlyImports($context);
        if ($imports === []) {
            return;
        }

        $namespaces = 0;
        foreach ($context->file->getTargetNodes() as $node) {
            $namespaces += $node->kind === NodeKind::Namespace ? 1 : 0;
        }

        $fixable = $namespaces <= 1;
        $findings = [];
        $mentions = [];
        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $text = $context->file->getText($trivia->span);
            foreach ($imports as $short => $import) {
                $mentions[$short] = ($mentions[$short] ?? 0) + self::mentions($text, $short);
            }

            foreach (Docblocks::tags($context->file, $trivia->span) as $tag) {
                $finding = in_array($tag->name, self::TAGS, strict: true)
                    ? $this->checkTag($tag, $imports, $fixable)
                    : null;
                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        }

        $this->report($context, $findings, $imports, $mentions);
    }

    /**
     * Reports each finding with its edits. An import whose every docblock
     * mention is rewritten is deleted too, in the first finding that names
     * it, since one statement can only be deleted once.
     *
     * @param list<array{Issue, list<string>}> $findings Each issue with the
     *   short names its edits rewrite, once per rewrite.
     * @param array<string, array{string, int, Node}> $imports
     * @param array<string, int> $mentions
     */
    private function report(LintContext $context, array $findings, array $imports, array $mentions): void
    {
        $rewritten = [];
        foreach ($findings as [, $names]) {
            foreach ($names as $short) {
                $rewritten[$short] = ($rewritten[$short] ?? 0) + 1;
            }
        }

        $deleted = [];
        foreach ($findings as [$issue, $names]) {
            foreach ($names as $short) {
                if (array_key_exists($short, $deleted) || ($rewritten[$short] ?? 0) !== ($mentions[$short] ?? 0)) {
                    continue;
                }

                $deleted[$short] = true;
                $issue = $issue->withEdit(ImportPlan::deleteStatement($context->file, $imports[$short][2]));
            }

            $context->report($issue);
        }
    }

    /**
     * How many times a docblock names the short name as a whole word.
     */
    private static function mentions(string $text, string $short): int
    {
        return (int) preg_match_all('/(?<![\\\\\w$])' . preg_quote($short, delimiter: '/') . '(?![\\w\\\\])/', $text);
    }

    /**
     * The single imports that no name in the code uses, keyed by the short
     * name that they introduce.
     *
     * @return array<string, array{string, int, Node}>
     */
    private function docblockOnlyImports(LintContext $context): array
    {
        $imports = $this->singleImports($context);
        if ($imports === []) {
            return [];
        }

        $uses = [];
        foreach ($context->file->getTargetNodes() as $node) {
            if ($node->kind !== NodeKind::Use) {
                continue;
            }

            $uses[] = $node;
        }

        // The resolved names are the names in the code. Docblocks have none.
        $used = [];
        foreach ($context->file->getResolvedNames() as $name) {
            if (!$name->imported || self::inside($uses, $name->span)) {
                continue;
            }

            $written = $context->file->getText($name->span);
            $used[strtolower(explode('\\', $written, limit: 2)[0])] = true;
        }

        foreach (array_keys($imports) as $short) {
            if (!array_key_exists(strtolower($short), $used)) {
                continue;
            }

            unset($imports[$short]);
        }

        return $imports;
    }

    /**
     * Whether the span lies in one of the nodes.
     *
     * @param list<Node> $nodes
     */
    private static function inside(array $nodes, Span $span): bool
    {
        foreach ($nodes as $node) {
            if ($span->start >= $node->span->start && $span->end <= $node->span->end) {
                return true;
            }
        }

        return false;
    }

    /**
     * Returns every single import in the file, keyed by the short name that
     * it introduces, with the offset where its statement ends and the
     * statement.
     *
     * @return array<string, array{string, int, Node}>
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
            $imports[$shortName] = [$fullyQualified, $use->span->end, $use];
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
     * The tag's issue when its type names one of the imports by its short
     * name, with an edit for each such name when the fix applies, and the
     * short names those edits rewrite.
     *
     * @param array<string, array{string, int, Node}> $imports
     * @param bool $fixable Whether the file allows the fix at all.
     * @return ?array{Issue, list<string>}
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function checkTag(DocblockTag $tag, array $imports, bool $fixable): ?array
    {
        [$type] = Docblocks::splitType($tag->content());
        if ($type === null) {
            return null;
        }

        // The edits need the type's offset, which is known when the type
        // starts the tag's first line.
        $line = $tag->lines[0];
        $indent = strspn($line->text, characters: " \t");
        $start = $fixable && substr($line->text, $indent, strlen($type)) === $type ? $line->offset + $indent : null;

        $first = null;
        $edits = [];
        $names = [];
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
                    $names[] = $member;
                }
            }

            $position += strlen($segment) + 1;
        }

        if ($first === null) {
            return null;
        }

        [$member, $fullyQualified] = $first;
        $issue = Issue::new(
            "{$member} is imported only for docblocks. Write \\{$fullyQualified} in the @{$tag->name} type and remove the import.",
            $tag->contentSpan(),
        )->withHelp('Coder 9 reports an import that only docblocks use as unused, and phpcbf deletes it.');
        foreach ($edits as $edit) {
            $issue = $issue->withEdit($edit);
        }

        return [$issue, $names];
    }
}
