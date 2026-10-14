<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

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
 * @internal
 */
final class FileDocblock
{
    /**
     * The declarations that Coder counts as a class.
     */
    private const CLASS_LIKES = [NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum];

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

            if (!Docblocks::isDirective($file, $trivia)) {
                return $trivia;
            }

            $start = $trivia->span->end;
        }

        return null;
    }

    /**
     * Whether the file has a PHP open tag. Coder checks every file with one,
     * a `.php` file too. A YAML file, which Mago parses as text outside PHP,
     * has none.
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
        $declarations = self::classLikes($file);
        if ($declarations === []) {
            return false;
        }

        if ($file->getNodes(NodeKind::Namespace) !== []) {
            return true;
        }

        if (count($declarations) > 1) {
            return false;
        }

        // Coder counts a function, or a method of an anonymous class, outside
        // the class. A closure or an arrow function does not count.
        $class = $declarations[0]->span;
        foreach ([...$file->getNodes(NodeKind::Function), ...$file->getNodes(NodeKind::Method)] as $function) {
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
        return $file->getNodes(NodeKind::Namespace) !== [] && count(self::classLikes($file)) === 1;
    }

    /**
     * The class, interface, trait and enum declarations of the file.
     *
     * @return list<Node>
     */
    private static function classLikes(SourceFile $file): array
    {
        $declarations = [];
        foreach (self::CLASS_LIKES as $kind) {
            $declarations = [...$declarations, ...$file->getNodes($kind)];
        }

        return $declarations;
    }
}
