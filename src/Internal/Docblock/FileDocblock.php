<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use amateescu\MagoDrupal\Internal\Cache\FileMemo;
use amateescu\MagoDrupal\Internal\Comment\CommentAttachment;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function preg_match;
use function substr;

/**
 * Finds the comment at the start of a file, and tells which files Coder
 * checks for a file docblock, as Drupal.Commenting.FileComment does.
 *
 * The single pass over the target nodes sorts them by kind in one match.
 *
 * @internal
 *
 * @mago-expect lint:kan-defect
 */
final class FileDocblock
{
    /**
     * The node kinds that a rule calling isClassFile() or
     * isNamespacedClassFile() must target, besides `Program`. Rust then puts
     * every node of these kinds in the file's target-node list, which the
     * worker builds before the rules run. One pass over that list replaces a
     * getNodes() call per kind, and each of those re-scans the whole tree.
     */
    public const DECLARATIONS = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::Namespace,
        NodeKind::Function,
        NodeKind::Method,
    ];

    /**
     * @var FileMemo<array{list<Node>, bool, list<Node>}>|null
     */
    private static ?FileMemo $memo = null;

    private function __construct() {}

    /**
     * The file's first comment when only whitespace comes between it and the
     * first opening tag, past any directive such as `// phpcs:ignoreFile`. A
     * directive is a tool instruction, not the file comment. Coder does not
     * look past one.
     *
     * Coder reads from the first opening tag, so text before it, such as a
     * `#!/usr/bin/env php` line or a byte order mark, does not count. A
     * comment after a second opening tag does not count either.
     */
    public static function firstComment(SourceFile $file): ?Trivia
    {
        $start = 0;
        $prefix = '';
        foreach ($file->getTrivia() as $trivia) {
            $prefix .= substr($file->contents, $start, $trivia->span->start - $start);
            if (preg_match('/^(?:(?!<\?php).)*<\?php\s*$/si', $prefix) !== 1) {
                return null;
            }

            if (!CommentAttachment::isDirective($file, $trivia)) {
                return $trivia;
            }

            $start = $trivia->span->end;
        }

        return null;
    }

    /**
     * Whether the file has a PHP open tag. Coder checks every file with one,
     * a `.php` file too. A YAML file, which Mago parses as text outside PHP,
     * doesn't have one.
     */
    public static function hasOpenTag(SourceFile $file): bool
    {
        return preg_match('/<\?(?:php\b|\s)/i', $file->contents) === 1;
    }

    /**
     * Whether Coder skips the file comment of the file. It skips a file that
     * has a class, interface, trait or enum and a namespace. It also skips a
     * file that has exactly one of them, no function or method outside it,
     * and no `@file` tag in any docblock.
     */
    public static function isClassFile(SourceFile $file): bool
    {
        [$declarations, $namespaced, $functions] = self::declarations($file);
        if ($declarations === []) {
            return false;
        }

        if ($namespaced) {
            return true;
        }

        if (count($declarations) > 1) {
            return false;
        }

        // Coder counts a function, or a method of an anonymous class, outside
        // the class. A closure or an arrow function does not count.
        $class = $declarations[0]->span;
        foreach ($functions as $function) {
            if (!$class->contains($function->span)) {
                return false;
            }
        }

        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind === TriviaKind::DocBlockComment && Docblocks::fileTag($file, $trivia->span)[0] !== null) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the file has a namespace and exactly one class, interface,
     * trait or enum, which Coder wants without a file comment.
     */
    public static function isNamespacedClassFile(SourceFile $file): bool
    {
        [$declarations, $namespaced] = self::declarations($file);

        return $namespaced && count($declarations) === 1;
    }

    /**
     * The class, interface, trait and enum declarations of the file, whether
     * it has a namespace, and its functions and methods. Coder counts the
     * first four as a class.
     *
     * @return array{list<Node>, bool, list<Node>}
     */
    private static function declarations(SourceFile $file): array
    {
        self::$memo ??= new FileMemo();

        return self::$memo->get($file, static fn(): array => self::collect($file));
    }

    /**
     * @return array{list<Node>, bool, list<Node>}
     */
    private static function collect(SourceFile $file): array
    {
        $classLikes = [];
        $namespaced = false;
        $functions = [];
        foreach ($file->getTargetNodes() as $node) {
            match ($node->kind) {
                NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum => $classLikes[] = $node,
                NodeKind::Function, NodeKind::Method => $functions[] = $node,
                NodeKind::Namespace => $namespaced = true,
                default => null,
            };
        }

        return [$classLikes, $namespaced, $functions];
    }
}
