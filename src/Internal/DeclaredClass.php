<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function array_slice;
use function ltrim;
use function strtolower;

/**
 * Finds the class a class node declares without the node's children.
 *
 * A lifecycle hook that asks for no subtree gets the node's span and the
 * file's resolved names. Every bare name in the span comes back resolved
 * against the namespace, imports flagged: the declaration itself, parents and
 * traits written without a `use`, but also called functions and constants.
 * The codebase says which of them is declared at that span.
 *
 * @internal
 */
final class DeclaredClass
{
    private function __construct() {}

    /**
     * The names mentioned inside the span, lowercased and keyed, and the
     * unimported ones in source order as declaration candidates. One walk of
     * the file's names serves both.
     *
     * @return array{array<string, true>, list<non-empty-string>}
     */
    public static function names(SourceFile $file, Node|Span|null $within): array
    {
        $mentions = [];
        $candidates = [];
        foreach ($file->getResolvedNames($within) as $name) {
            $resolved = ltrim($name->name, characters: '\\');
            if ($resolved === '') {
                continue;
            }

            $key = strtolower($resolved);
            if ($name->imported || array_key_exists($key, $mentions)) {
                $mentions[$key] = true;
                continue;
            }

            $mentions[$key] = true;
            $candidates[] = $resolved;
        }

        return [$mentions, $candidates];
    }

    /**
     * @return list<non-empty-string>
     */
    public static function candidates(SourceFile $file, Node|Span|null $within): array
    {
        return self::names($file, $within)[1];
    }

    /**
     * The metadata of the class-like declared by the node, or null when the
     * codebase has none at that span.
     *
     * @param list<non-empty-string>|null $candidates Precomputed by `names()`.
     */
    public static function resolve(
        SourceFile $file,
        Node $node,
        Codebase $codebase,
        ?array $candidates = null,
    ): ?ClassLikeMetadata {
        $candidates ??= self::candidates($file, $node);
        if ($candidates === []) {
            return null;
        }

        // The declared name comes first in source order unless an unimported
        // attribute precedes it, so the other names are only fetched then.
        $first = $codebase->getClassLike($candidates[0]);
        if ($first !== null && self::declaredAt($first, $file, $node->span)) {
            return $first;
        }

        $others = array_slice($candidates, offset: 1);
        foreach ($others === [] ? [] : $codebase->getMultipleClassLikes($others) as $class) {
            if ($class !== null && self::declaredAt($class, $file, $node->span)) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Whether the class-like is the one declared at the span. Locations name
     * the file the way the snapshot does, and a declaration's location covers
     * its attributes like the node span does.
     */
    public static function declaredAt(ClassLikeMetadata $class, SourceFile $file, Span $span): bool
    {
        $location = $class->location;

        return $location->file === $file->path && $location->span->start === $span->start;
    }
}
