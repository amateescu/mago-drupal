<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function array_key_exists;
use function array_slice;
use function count;
use function end;
use function explode;
use function implode;
use function ltrim;
use function preg_match;
use function preg_quote;
use function str_replace;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Plans the imports that let a file write namespaced classes by their short
 * name.
 *
 * Each class gets one batch of edits: the `use` statement and the short
 * name at every place the file writes the class in full. The batch goes on
 * the class's first report, since an insert per report would import the
 * class once per place. A class is left alone when its short name could
 * mean something else in the file: another import, a class of that name, a
 * name that already resolves elsewhere, or a docblock that writes the short
 * name in the same case. Only a file with one namespace, declared without
 * braces, gets edits.
 *
 * A name written through the import of its namespace, as `Psr7\Utils` with
 * `use GuzzleHttp\Psr7;`, can leave that import unused. When one class's
 * batch rewrites every name written through it, the batch drops the import
 * too, or puts the new import in its place.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ImportPlan
{
    private function __construct() {}

    /**
     * The edits for each class, keyed by the start offset of its first
     * report.
     *
     * @param list<Node> $namespaces
     * @param list<Node> $uses
     * @param list<Node> $names The names the rule reports, in source order.
     * @return array<int, list<TextEdit>>
     */
    public static function edits(SourceFile $file, array $namespaces, array $uses, array $names): array
    {
        $header = count($namespaces) === 1 ? self::header($file, $namespaces[0]) : null;
        $imports = self::imports($file, $uses);
        if ($header === null || $imports === null) {
            return [];
        }

        [$byClass, $aliases, $statements] = $imports;

        [$current, $headerEnd] = $header;
        if (self::importsFollowCode($file, $headerEnd, $uses)) {
            return [];
        }

        // The import goes on its own line after the last import, or after
        // the namespace line, past any comment that ends that line.
        $last = end($uses);
        $eol = LineEnding::of($file->contents);
        $insertAt = self::lineEnd($file->contents, $last === false ? $headerEnd : $last->span->end);
        $prefix = $last === false ? $eol . $eol : $eol;

        $groups = self::groups($file, $names);
        $plans = [];
        $claimed = [];
        foreach ($groups as $key => $occurrences) {
            $class = $occurrences[0][1];
            $segments = explode('\\', $class);
            $short = end($segments);
            $shortKey = strtolower($short);
            // The first class to want a short name keeps it.
            if (array_key_exists($shortKey, $claimed)) {
                continue;
            }

            $claimed[$shortKey] = true;
            $alias = $byClass[$key] ?? null;
            if (
                $alias === null
                && (array_key_exists($shortKey, $aliases) || self::conflicts($file, $uses, $short, $class))
            ) {
                continue;
            }

            $namespace = implode('\\', array_slice($segments, offset: 0, length: -1));
            $import = $alias === null && strtolower($namespace) !== strtolower($current) ? "use {$class};" : null;
            $stale = self::staleImport($file, $uses, $statements, $occurrences);
            $edits = self::importEdits($file, $import, $stale, $insertAt, $prefix);
            foreach ($occurrences as [$name]) {
                $edits[] = TextEdit::replace($name->span, $alias ?? $short);
            }

            $plans[$occurrences[0][0]->span->start] = $edits;
        }

        return $plans;
    }

    /**
     * The reported names of each class, keyed by the lowercased class, in
     * the order the classes first come up. A constant has no class to
     * import and is left out.
     *
     * @param list<Node> $names
     * @return array<string, non-empty-list<array{Node, string}>>
     */
    private static function groups(SourceFile $file, array $names): array
    {
        $groups = [];
        foreach ($names as $name) {
            $resolved = $file->getResolvedName($name);
            if ($resolved === null || self::isConstant($file, $name)) {
                continue;
            }

            $class = ltrim($resolved->name, characters: '\\');
            $groups[strtolower($class)][] = [$name, $class];
        }

        return $groups;
    }

    /**
     * The offset of the line break that ends the line holding the offset,
     * before a `\r` of it, or the end of the file.
     */
    private static function lineEnd(string $contents, int $offset): int
    {
        $break = strpos($contents, needle: "\n", offset: $offset);
        if ($break === false) {
            return strlen($contents);
        }

        return $break > 0 && $contents[$break - 1] === "\r" ? $break - 1 : $break;
    }

    /**
     * Whether code comes before one of the imports. A new import goes after
     * the last one, and the names in that code would then resolve without
     * it.
     *
     * @param list<Node> $uses
     */
    private static function importsFollowCode(SourceFile $file, int $headerEnd, array $uses): bool
    {
        $cursor = $headerEnd;
        $trivia = $file->getTrivia();
        foreach ($uses as $use) {
            $gap = substr($file->contents, $cursor, $use->span->start - $cursor);
            foreach ($trivia as $comment) {
                if ($comment->span->start < $cursor || $comment->span->end > $use->span->start) {
                    continue;
                }

                $gap = str_replace($file->getText($comment->span), replace: '', subject: $gap);
            }

            if (trim($gap) !== '') {
                return true;
            }

            $cursor = $use->span->end;
        }

        return false;
    }

    /**
     * The namespace's name and the offset after its `;`, or null for a
     * braced namespace.
     *
     * @return array{string, int}|null
     */
    private static function header(SourceFile $file, Node $namespace): ?array
    {
        $text = $file->getText($namespace);
        $semicolon = strpos($text, needle: ';');
        $brace = strpos($text, needle: '{');
        if ($semicolon === false || $brace !== false && $brace < $semicolon) {
            return null;
        }

        $name = trim(substr($text, offset: strlen('namespace'), length: $semicolon - strlen('namespace')));

        return [$name, $namespace->span->start + $semicolon + 1];
    }

    /**
     * Each imported class, lowercased, with the name the file uses for it,
     * the set of those names, lowercased, and the statements that import
     * one name, by that name, lowercased. Null when a grouped import makes
     * the names hard to tell.
     *
     * @param list<Node> $uses
     * @return array{array<string, string>, array<string, true>, array<string, Node>}|null
     */
    private static function imports(SourceFile $file, array $uses): ?array
    {
        $byClass = [];
        $aliases = [];
        $statements = [];
        foreach ($uses as $use) {
            $items = $file->getFirstDescendant($use, NodeKind::UseItems);
            $sequence = $items === null ? null : $file->getChildren($items)[0] ?? null;
            if ($sequence === null) {
                return null;
            }

            if ($sequence->kind === NodeKind::TypedUseItemSequence) {
                continue;
            }

            if ($sequence->kind !== NodeKind::UseItemSequence) {
                return null;
            }

            $items = 0;
            $name = '';
            foreach ($file->getChildren($sequence) as $item) {
                if ($item->kind !== NodeKind::UseItem) {
                    continue;
                }

                $items++;

                $identifier = $file->getFirstDescendant($item, NodeKind::Identifier);
                $class = $identifier === null ? '' : ltrim($file->getText($identifier), characters: '\\');
                $alias = $file->getFirstDescendant($item, NodeKind::UseItemAlias);
                $local = $alias === null ? null : $file->getFirstDescendant($alias, NodeKind::LocalIdentifier);
                $segments = explode('\\', $class);
                $name = $local === null ? end($segments) : $file->getText($local);
                $byClass[strtolower($class)] = $name;
                $aliases[strtolower($name)] = true;
            }

            if ($items === 1) {
                $statements[strtolower($name)] = $use;
            }
        }

        return [$byClass, $aliases, $statements];
    }

    /**
     * The edit for the batch's import statement: the new import, the new
     * import in place of the one the batch leaves unused, or the removal of
     * that one when the class needs no import.
     *
     * @return list<TextEdit>
     */
    private static function importEdits(
        SourceFile $file,
        ?string $import,
        ?Node $stale,
        int $insertAt,
        string $prefix,
    ): array {
        if ($import === null) {
            return $stale === null ? [] : [self::deleteStatement($file, $stale)];
        }

        return [
            $stale === null ? TextEdit::insert($insertAt, $prefix . $import) : TextEdit::replace($stale->span, $import),
        ];
    }

    /**
     * The import that the batch leaves unused, or null. That is the import
     * a reported name is written through, as `use GuzzleHttp\Psr7;` for
     * `Psr7\Utils`, when no other name in the file goes through it and no
     * docblock writes it in the same case.
     *
     * @param list<Node> $uses
     * @param array<string, Node> $statements
     * @param non-empty-list<array{Node, string}> $occurrences
     */
    private static function staleImport(SourceFile $file, array $uses, array $statements, array $occurrences): ?Node
    {
        $local = null;
        $starts = [];
        foreach ($occurrences as [$name]) {
            $starts[$name->span->start] = true;
            $written = trim($file->getText($name));
            if (!str_starts_with($written, '\\')) {
                $local ??= explode('\\', $written, limit: 2)[0];
            }
        }

        $statement = $local === null ? null : $statements[strtolower($local)] ?? null;
        if ($local === null || $statement === null) {
            return null;
        }

        foreach ($file->getResolvedNames() as $resolved) {
            if (array_key_exists($resolved->span->start, $starts) || self::inside($uses, $resolved->span->start)) {
                continue;
            }

            $written = $file->getText($resolved->span);
            if (strtolower(explode('\\', $written, limit: 2)[0]) === strtolower($local)) {
                return null;
            }
        }

        $pattern = '/(?<![\w\\\\$])' . preg_quote($local, delimiter: '/') . '(?![\w])/';
        foreach ($file->getTrivia() as $trivia) {
            if (
                $trivia->kind === TriviaKind::DocBlockComment
                && preg_match($pattern, $file->getText($trivia->span)) === 1
            ) {
                return null;
            }
        }

        return $statement;
    }

    /**
     * Removes a use statement, with its line when nothing else is on it.
     */
    public static function deleteStatement(SourceFile $file, Node $use): TextEdit
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
     * Whether the short name already means something else in the file: a
     * name written with it as its first part that resolves to another class,
     * which covers a class declared under that name, or a docblock that
     * writes it in the same case.
     *
     * @param list<Node> $uses
     */
    private static function conflicts(SourceFile $file, array $uses, string $short, string $class): bool
    {
        $key = strtolower($short);
        $target = strtolower($class);
        foreach ($file->getResolvedNames() as $resolved) {
            $written = ltrim($file->getText($resolved->span), characters: '\\');
            if (str_starts_with(strtolower($written), 'namespace\\')) {
                $written = substr($written, offset: 10);
            }

            if (
                strtolower(explode('\\', $written, limit: 2)[0]) !== $key
                || self::inside($uses, $resolved->span->start)
            ) {
                continue;
            }

            $resolvedName = strtolower(ltrim($resolved->name, characters: '\\'));
            if ($resolvedName !== $target && !str_starts_with($resolvedName, $target . '\\')) {
                return true;
            }
        }

        // Anywhere in a docblock, since a type may spread over several
        // lines and an annotation names a class too. PHP ignores the case of
        // a class name, but a docblock writes a type in its own case, and
        // the same word in lowercase is prose, as in "Loads the node".
        $pattern = '/(?<![\w\\\\$])' . preg_quote($short, delimiter: '/') . '(?![\w\\\\])/';
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
     * Whether the name names a constant, as in `\Foo\BAR`, which a class
     * import cannot bring in.
     */
    private static function isConstant(SourceFile $file, Node $name): bool
    {
        $parent = $file->getParent($name);
        while ($parent !== null && $parent->kind === NodeKind::Identifier) {
            $parent = $file->getParent($parent);
        }

        return $parent !== null && $parent->kind === NodeKind::ConstantAccess;
    }

    /**
     * @param list<Node> $nodes
     */
    private static function inside(array $nodes, int $offset): bool
    {
        foreach ($nodes as $node) {
            if ($offset >= $node->span->start && $offset < $node->span->end) {
                return true;
            }
        }

        return false;
    }
}
