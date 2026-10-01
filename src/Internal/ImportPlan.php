<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Reporting\TextEdit;
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
 * name. Only a file with one namespace, declared without braces, gets edits.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
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

        [$byClass, $aliases] = $imports;

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

            $edits = [];
            $namespace = implode('\\', array_slice($segments, offset: 0, length: -1));
            if ($alias === null && strtolower($namespace) !== strtolower($current)) {
                $edits[] = TextEdit::insert($insertAt, "{$prefix}use {$class};");
            }

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
     * and the set of those names, lowercased. Null when a grouped import
     * makes the names hard to tell.
     *
     * @param list<Node> $uses
     * @return array{array<string, string>, array<string, true>}|null
     */
    private static function imports(SourceFile $file, array $uses): ?array
    {
        $byClass = [];
        $aliases = [];
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

            foreach ($file->getChildren($sequence) as $item) {
                if ($item->kind !== NodeKind::UseItem) {
                    continue;
                }

                $identifier = $file->getFirstDescendant($item, NodeKind::Identifier);
                $class = $identifier === null ? '' : ltrim($file->getText($identifier), characters: '\\');
                $alias = $file->getFirstDescendant($item, NodeKind::UseItemAlias);
                $local = $alias === null ? null : $file->getFirstDescendant($alias, NodeKind::LocalIdentifier);
                $segments = explode('\\', $class);
                $name = $local === null ? end($segments) : $file->getText($local);
                $byClass[strtolower($class)] = $name;
                $aliases[strtolower($name)] = true;
            }
        }

        return [$byClass, $aliases];
    }

    /**
     * Whether the short name already means something else in the file: a
     * name written with it as its first part that resolves to another class,
     * which covers a class declared under that name, or a docblock tag that
     * writes it.
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
        // lines and an annotation names a class too.
        $pattern = '/(?<![\w\\\\$])' . preg_quote($short, delimiter: '/') . '(?![\w\\\\])/i';
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
