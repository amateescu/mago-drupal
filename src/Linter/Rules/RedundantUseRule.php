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
use Mago\Sdk\Syntax\ResolvedName;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function array_key_exists;
use function explode;
use function implode;
use function ltrim;
use function preg_match;
use function preg_quote;
use function str_contains;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Reports a use statement that imports a class with no namespace.
 *
 * Ports Drupal.Classes.UseGlobalClass. Drupal writes `\Exception` at the call
 * site and does not import it. The import and its usages move together, so
 * the fix removes the import and writes every reference to the class in full.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class RedundantUseRule implements Rule
{
    /**
     * A statement that imports one name with no namespace, with or without an
     * alias.
     */
    private const SINGLE_GLOBAL_IMPORT = '/^use\s+\\\\?[A-Za-z_][A-Za-z0-9_]*(?:\s+as\s+[A-Za-z_][A-Za-z0-9_]*)?\s*;/i';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/redundant-use',
            name: 'Redundant use statement',
            description: 'Reports a use statement that imports a class from the global namespace.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // A Use dispatch sees only the statement itself. The fix rewrites
            // the references to the class, which only the Program pass sees,
            // so the Program pass reads the use statements from the target
            // list and their own dispatches do nothing.
            targets: [NodeKind::Program, NodeKind::Use],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $file = $context->file;
        $resolved = null;
        $uses = [];
        foreach ($file->getTargetNodes() as $node) {
            if ($node->kind !== NodeKind::Use) {
                continue;
            }

            $uses[] = $node;
        }

        foreach ($uses as $use) {
            $sequence = self::sequence($file, $use);
            if ($sequence === null) {
                continue;
            }

            $global = [];
            $kept = [];
            foreach ($file->getChildren($sequence) as $item) {
                if ($item->kind !== NodeKind::UseItem) {
                    continue;
                }

                $class = self::globalClass($file, $item);
                if ($class === null) {
                    $kept[] = $file->getText($item);
                    continue;
                }

                $global[] = [$item, $class, self::alias($file, $item) ?? $class];
            }

            if ($global === []) {
                continue;
            }

            $aliases = [];
            foreach ($global as [, $class, $alias]) {
                $aliases[strtolower($alias)] = $class;
            }

            // A docblock type that names a removed import by its short name
            // would change meaning, so the fix waits. `drupal/doc-type-namespace`
            // reports that type first.
            $resolved ??= $file->getResolvedNames();
            $edits = self::documented($file, $aliases)
                ? []
                : [
                    $kept === []
                        ? self::deleteStatement($file, $use)
                        : TextEdit::replace($sequence->span, implode(', ', $kept)),
                    ...self::references($file, $uses, $aliases, $resolved),
                ];
            $this->report($context, $global, $edits);
        }
    }

    /**
     * Reports each global import of one statement. The first report holds
     * the fix for the whole statement, since removing two items of one list
     * separately would give overlapping edits.
     *
     * @param list<array{Node, string, string}> $global Each global item with
     *   its class and the name the file uses for it.
     * @param list<TextEdit> $edits
     */
    private function report(LintContext $context, array $global, array $edits): void
    {
        foreach ($global as [$item, $class]) {
            $issue = Issue::new("Do not import the global class {$class}.", $item->span)->withHelp(
                "Remove the use statement and write \\{$class} at each usage. "
                . 'If you remove only the import, the references break.',
            );
            foreach ($edits as $edit) {
                $issue = $issue->withEdit($edit);
            }

            $context->report($issue);
            $edits = [];
        }
    }

    /**
     * The edits that write each reference to a removed import in full.
     *
     * @param list<Node> $uses Every use statement, whose names stay as
     *   they are.
     * @param array<string, string> $aliases Lowercased name the file uses to
     *   the class it stands for.
     * @param list<ResolvedName> $resolved
     * @return list<TextEdit>
     */
    private static function references(SourceFile $file, array $uses, array $aliases, array $resolved): array
    {
        $edits = [];
        foreach ($resolved as $name) {
            if (!$name->imported || self::inside($uses, $name->span)) {
                continue;
            }

            $written = $file->getText($name->span);
            $first = strtolower(explode('\\', $written, limit: 2)[0]);
            if (!array_key_exists($first, $aliases)) {
                continue;
            }

            // The name went through this import only when it resolves under
            // the imported class. A clash with another import of the same
            // short name in a different namespace block resolves elsewhere.
            $target = ltrim($name->name, characters: '\\');
            $head = strtolower(explode('\\', $target, limit: 2)[0]);
            if ($head !== strtolower($aliases[$first])) {
                continue;
            }

            $edits[] = TextEdit::replace($name->span, '\\' . $target);
        }

        return $edits;
    }

    /**
     * Whether the span lies in one of the nodes.
     *
     * @param list<Node> $nodes
     */
    private static function inside(array $nodes, Span $span): bool
    {
        foreach ($nodes as $node) {
            if ($node->span->contains($span)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a docblock in the file names one of the aliases without a
     * leading backslash, anywhere in its text: a type spread over several
     * lines, an annotation or prose. Prose only costs the fix.
     *
     * @param array<string, string> $aliases
     */
    private static function documented(SourceFile $file, array $aliases): bool
    {
        $names = [];
        foreach ($aliases as $alias => $_) {
            $names[] = preg_quote($alias, delimiter: '/');
        }

        $pattern = '/(?<![\w\\\\$])(?:' . implode('|', $names) . ')(?![\w])/i';
        foreach ($file->getTrivia() as $trivia) {
            if (
                $trivia->kind === TriviaKind::DocBlockComment
                && preg_match($pattern, $file->getText($trivia->span)) === 1
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Removes the statement, with its line when nothing else is on it.
     */
    private static function deleteStatement(SourceFile $file, Node $use): TextEdit
    {
        $contents = $file->contents;
        $start = $use->span->start;
        $end = $use->span->end;
        $lineStart = strrpos(substr($contents, offset: 0, length: $start), needle: "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        $before = substr($contents, $lineStart, $start - $lineStart);
        $lineEnd = strpos($contents, needle: "\n", offset: $end);
        $after = $lineEnd === false ? substr($contents, $end) : substr($contents, $end, $lineEnd - $end);
        if (trim($before) === '' && trim($after) === '') {
            $start = $lineStart;
            $end = $lineEnd === false ? strlen($contents) : $lineEnd + 1;
            // The only import between two blank lines leaves them side by
            // side, so one of them goes too.
            $below = [];
            if (
                preg_match('/\r?\n[ \t]*\r?\n$/', substr($contents, offset: 0, length: $start)) === 1
                && preg_match('/\G[ \t]*\r?\n/', $contents, $below, offset: $end) === 1
            ) {
                $end += strlen($below[0]);
            }
        }

        return TextEdit::delete(new Span($start, $end));
    }

    /**
     * The statement's list of plain class imports, or null for a function or
     * constant import, a grouped import, or a statement that cannot import a
     * global class.
     */
    private static function sequence(SourceFile $file, Node $use): ?Node
    {
        // A single namespaced import, a grouped import and a function or
        // constant import cannot name a global class. Together they are
        // nearly every use statement. The text shows this without a node
        // read. The rule still walks a statement that lists several names.
        $text = $file->getText($use);
        if (!str_contains($text, ',') && preg_match(self::SINGLE_GLOBAL_IMPORT, $text) !== 1) {
            return null;
        }

        // `use function` and `use const` parse as a TypedUseItemSequence. A
        // grouped import parses as a Mixed/TypedUseItemList, and its items are
        // under a namespace prefix. Only a plain sequence can import a global
        // class.
        $items = $file->getFirstDescendant($use, NodeKind::UseItems);
        $sequence = $items === null ? null : $file->getChildren($items)[0] ?? null;

        return $sequence !== null && $sequence->kind === NodeKind::UseItemSequence ? $sequence : null;
    }

    /**
     * The class an item imports when it has no namespace, without the
     * leading backslash.
     */
    private static function globalClass(SourceFile $file, Node $item): ?string
    {
        // Only the item's own identifier shows whether the class is
        // namespaced. An alias is also a local identifier. A search of the
        // descendants reports `use Bar\Baz as Qux` as global.
        $identifier = $file->getFirstDescendant($item, NodeKind::Identifier);
        $name = $identifier === null ? null : $file->getChildren($identifier)[0] ?? null;
        if ($name === null) {
            return null;
        }

        // `use \Exception;` is the same global import with a leading
        // backslash. It parses as a fully qualified identifier.
        $class = ltrim($file->getText($name), characters: '\\');
        $global =
            $name->kind === NodeKind::LocalIdentifier
            || $name->kind === NodeKind::FullyQualifiedIdentifier && !str_contains($class, '\\');

        return $global ? $class : null;
    }

    /**
     * The alias an item gives its class, as in `use Exception as Failure`.
     */
    private static function alias(SourceFile $file, Node $item): ?string
    {
        $alias = $file->getFirstDescendant($item, NodeKind::UseItemAlias);
        $name = $alias === null ? null : $file->getFirstDescendant($alias, NodeKind::LocalIdentifier);

        return $name === null ? null : $file->getText($name);
    }
}
