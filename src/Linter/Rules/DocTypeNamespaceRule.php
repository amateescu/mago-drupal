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
use function strpbrk;
use function strrpos;
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
     * short name that it introduces.
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

        // Most types are plain. A plain type is one lookup.
        if (strpbrk($type, characters: '|<[?') === false) {
            $this->reportImported($context, $tag, $type, $imports);

            return;
        }

        $members = preg_split('/\|/', $type);
        foreach ($members === false ? [] : $members as $member) {
            $member = ltrim($member, characters: '?');
            $member = explode('<', $member, limit: 2)[0];
            $member = explode('[', $member, limit: 2)[0];

            if ($this->reportImported($context, $tag, $member, $imports)) {
                return;
            }
        }
    }

    /**
     * Reports a type member that is an imported short name.
     *
     * @param array<string, string> $imports
     */
    private function reportImported(LintContext $context, DocblockTag $tag, string $member, array $imports): bool
    {
        $fullyQualified = $imports[$member] ?? null;
        if ($fullyQualified === null) {
            return false;
        }

        $context->report(Issue::new(
            "The @{$tag->name} type must be fully qualified. Use \\{$fullyQualified} instead of {$member}.",
            $tag->contentSpan(),
        ));

        return true;
    }
}
