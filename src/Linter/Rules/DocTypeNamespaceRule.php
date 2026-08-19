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
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function explode;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_split;
use function str_contains;
use function strrpos;
use function substr;

/**
 * Reports a doc comment type written as a use-imported class's short name.
 *
 * Ports Drupal.Commenting.DataTypeNamespace. Only single, unaliased imports
 * are handled, matching what the ported sniff itself reads reliably; a
 * grouped or comma-separated `use` statement is left alone.
 *
 * Targets the whole file rather than one `use` statement at a time: reading
 * every import once and then walking every docblock once, instead of
 * re-walking every docblock after each import, turns a cost proportional to
 * imports times docblocks times docblock lines into one proportional to
 * imports plus docblocks times lines. A large file with dozens of imports
 * and docblocks made this the single most expensive rule in the family
 * before the change. One simplification that comes with it: an import is
 * treated as in scope for the whole file, not just the docblocks below it.
 * In practice that means a docblock *above* the imports is now in scope too,
 * most commonly a procedural file's own `@file` block, not the unusual
 * placement of a `use` statement after code that already referenced it.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocTypeNamespaceRule implements Rule
{
    private const TAGS = ['param', 'return', 'var', 'throws'];

    public function getDefinition(): RuleDefinition
    {
        // `Use` is declared as a target so Rust collects every import into
        // the file's pre-computed target-node list, which the Program pass
        // reads for free; the per-import dispatches are no-ops. Asking the
        // node table instead was one full, unindexed re-scan per file.
        return new RuleDefinition(
            code: 'drupal/doc-type-namespace',
            name: 'Doc comment type namespace',
            description: 'Reports @param, @return, @var and @throws types written as a use-imported short name instead of the fully qualified name.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program, NodeKind::Use],
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

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            foreach (Docblocks::tags($context->file, $trivia->span) as $tag) {
                if (!in_array($tag->name, self::TAGS, strict: true)) {
                    continue;
                }

                $this->checkTag($context, $tag, $imports);
            }
        }
    }

    /**
     * Returns every single, unaliased import in the file, keyed by the
     * short name it introduces.
     *
     * @return array<string, string>
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
            $imports[$shortName] = $fullyQualified;
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
     * @param array<string, string> $imports
     */
    private function checkTag(LintContext $context, DocblockTag $tag, array $imports): void
    {
        [$type] = Docblocks::splitType($tag->content());
        if ($type === null) {
            return;
        }

        $members = preg_split('/\|/', $type);
        foreach ($members === false ? [] : $members as $member) {
            $member = ltrim($member, characters: '?');
            $member = explode('<', $member, limit: 2)[0];
            $member = explode('[', $member, limit: 2)[0];

            $fullyQualified = $imports[$member] ?? null;
            if ($fullyQualified === null) {
                continue;
            }

            $context->report(Issue::new(
                "Data types in @{$tag->name} tags need to be fully namespaced: use \\{$fullyQualified} instead of {$member}.",
                $tag->contentSpan(),
            ));

            return;
        }
    }
}
