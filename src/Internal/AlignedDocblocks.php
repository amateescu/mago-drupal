<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;

use function ctype_space;
use function preg_match;
use function strcasecmp;
use function substr;
use function trim;

/**
 * Tells which docblocks the DocCommentAlignment sniff of Coder checks.
 *
 * @internal
 */
final class AlignedDocblocks
{
    /**
     * What a docblock may be in front of for Coder to check its stars. The
     * keyword is the next token after the docblock and any comments.
     */
    private const DECLARATION_PATTERN =
        '/\G(?>\s+|\/\/[^\r\n]*|#(?!\[)[^\r\n]*|\/\*.*?\*\/)*+'
            . '(?:class|interface|function|public|private|protected|static|abstract|var)(?![\w\x80-\xff])/is';

    private function __construct() {}

    /**
     * Whether Coder checks the stars of a docblock.
     *
     * It does when a declaration keyword comes next, or when the docblock is
     * the first thing after the PHP open tag. That is how it tells a
     * declaration's docblock and a file docblock from the ones in the middle
     * of code.
     */
    public static function isChecked(SourceFile $file, Span $span): bool
    {
        return (
            preg_match(self::DECLARATION_PATTERN, $file->contents, offset: $span->end) === 1
            || self::followsOpenTag($file, $span)
        );
    }

    /**
     * Whether only whitespace and comments sit between the PHP open tag and
     * the docblock.
     */
    private static function followsOpenTag(SourceFile $file, Span $span): bool
    {
        $trivia = $file->getTrivia();
        $index = Docblocks::lastTriviaIndexStartingBefore($trivia, $span->start);
        $start = $span->start;
        while ($index !== null && $index > 0) {
            $previous = $trivia[$index - 1]->span;
            if (trim(substr($file->contents, $previous->end, $start - $previous->end)) !== '') {
                break;
            }

            $start = $previous->start;
            $index--;
        }

        $end = $start;
        while ($end > 0 && ctype_space($file->contents[$end - 1])) {
            $end--;
        }

        return $end >= 5 && strcasecmp(substr($file->contents, $end - 5, length: 5), '<?php') === 0;
    }
}
