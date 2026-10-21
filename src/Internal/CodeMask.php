<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function array_slice;
use function in_array;
use function preg_replace;
use function str_repeat;
use function strcspn;
use function strlen;
use function strpbrk;
use function substr;

use const T_COMMENT;
use const T_CONSTANT_ENCAPSED_STRING;
use const T_DOC_COMMENT;
use const T_ENCAPSED_AND_WHITESPACE;
use const T_INLINE_HTML;

/**
 * PHP source with the parts that aren't code turned into spaces, for a regex
 * that reads code structure.
 *
 * The tokenizer decides what is code, so a `?>` inside a `//` comment, a
 * `#[` attribute, a heredoc and a string that holds code-like text all come
 * out the way PHP reads them. A mask has the same length as the source and
 * keeps its line breaks, so an offset in one is the same offset in the other.
 *
 * The comments mask blanks the comments and the text outside the PHP tags.
 * The code mask also blanks the text of string literals and of heredoc and
 * nowdoc bodies, and keeps their quotes and labels. A variable inside a
 * double-quoted string or a heredoc is code, and stays in both.
 *
 * @internal
 */
final class CodeMask
{
    /**
     * The tokens that the comments mask blanks.
     */
    private const COMMENTS = [T_COMMENT, T_DOC_COMMENT, T_INLINE_HTML];

    /**
     * The tokens that the code mask blanks.
     */
    private const LITERALS = [
        T_COMMENT,
        T_DOC_COMMENT,
        T_INLINE_HTML,
        T_ENCAPSED_AND_WHITESPACE,
        T_CONSTANT_ENCAPSED_STRING,
    ];

    /**
     * The open tag that a fragment gets for the tokenizer.
     */
    private const OPEN_TAG = '<?php ';

    /**
     * @var FileMemo<string>|null
     */
    private static ?FileMemo $memo = null;

    private function __construct() {}

    /**
     * The file with its comments blanked, kept for the last file.
     */
    public static function comments(string $contents): string
    {
        return self::ofFile($contents, self::COMMENTS, item: 'comments');
    }

    /**
     * The file with its comments and the text of its literals blanked, kept
     * for the last file.
     */
    public static function code(string $contents): string
    {
        return self::ofFile($contents, self::LITERALS, item: 'code');
    }

    /**
     * A piece of code with its comments blanked. The piece starts and ends
     * between two tokens, as a node's text or the gap between two nodes does.
     */
    public static function fragmentComments(string $code): string
    {
        return self::mask(self::fragmentTokens($code), self::COMMENTS);
    }

    /**
     * A piece of code with its comments and the text of its literals blanked.
     */
    public static function fragmentCode(string $code): string
    {
        return self::mask(self::fragmentTokens($code), self::LITERALS);
    }

    /**
     * @param list<int> $blanked
     * @param string $item The memo item of the mask.
     */
    private static function ofFile(string $contents, array $blanked, string $item): string
    {
        self::$memo ??= new FileMemo();
        $memo = self::$memo;

        return $memo->has($contents, $item)
            ? $memo->value($item)
            : $memo->keep($contents, self::mask(PhpTokens::of($contents), $blanked), $item);
    }

    /**
     * @return list<PhpToken>
     */
    private static function fragmentTokens(string $code): array
    {
        return array_slice(PhpToken::tokenize(self::OPEN_TAG . $code), offset: 1);
    }

    /**
     * @param list<PhpToken> $tokens
     * @param list<int> $blanked
     */
    private static function mask(array $tokens, array $blanked): string
    {
        $mask = '';
        foreach ($tokens as $token) {
            $text = $token->text;
            if (!in_array($token->id, $blanked, strict: true)) {
                $mask .= $text;
                continue;
            }

            $mask .= $token->id === T_CONSTANT_ENCAPSED_STRING ? self::blankQuoted($text) : self::blank($text);
        }

        return $mask;
    }

    /**
     * A quoted string with its quotes, and a `b` prefix, kept.
     */
    private static function blankQuoted(string $text): string
    {
        $open = strcspn($text, characters: '\'"') + 1;

        return (
            substr($text, offset: 0, length: $open)
            . self::blank(substr($text, $open, strlen($text) - $open - 1))
            . substr($text, offset: -1)
        );
    }

    /**
     * The text with every character but a line break turned into a space.
     */
    private static function blank(string $text): string
    {
        return strpbrk($text, characters: "\r\n") === false
            ? str_repeat(' ', strlen($text))
            : (string) preg_replace('/[^\r\n]/', replacement: ' ', subject: $text);
    }
}
