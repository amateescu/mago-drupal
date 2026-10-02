<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocType;
use amateescu\MagoDrupal\Internal\FileGate;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function in_array;
use function ltrim;
use function preg_match;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function strlen;
use function strpos;
use function strrpos;
use function strspn;
use function substr;
use function trim;

/**
 * Checks the style and word order of an inline `@var` type declaration.
 *
 * Ports Drupal.Commenting.InlineVariableComment. A `//` or `#` comment that
 * has `@var` in it must use `/** *\/` delimiters instead. One exception: a
 * comment directly before a declaration is the docblock of that declaration
 * in the wrong style. `drupal/class-comment`, `drupal/file-comment` and
 * `drupal/variable-comment` already report that problem. A real `@var`
 * docblock tag must have the type before the variable name.
 *
 * A variable name written before the type moves after it, when the whole
 * type can be read. A comment that holds only the tag, alone on its line,
 * becomes a `/** *\/` docblock. Both fixes are potentially unsafe, because
 * the analyzers start to trust the type.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class InlineVariableCommentRule implements Rule
{
    private const DECLARATION_KEYWORDS = '/^(class|interface|trait|enum|function|public|private|protected|final|static|abstract|const|var|include|require)\b/';

    private const LOOKAHEAD = 40;

    /**
     * A variable name at the start of the text.
     */
    private const VARIABLE = '/^\$[A-Za-z_\x80-\xff][\w\x80-\xff]*/';

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inline-variable-comment',
            name: 'Inline variable comment',
            description: 'Checks the style and word order of an inline @var type declaration.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Both branches look for the literal tag. A file without "@var"
        // cannot match.
        $this->gate ??= new FileGate(needles: ['@var']);
        if (!$this->gate->passes($context->file)) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind === TriviaKind::DocBlockComment) {
                foreach (Docblocks::tags($context->file, $trivia->span) as $tag) {
                    if ($tag->name !== 'var' || preg_match('/^\$/', $tag->content()) !== 1) {
                        continue;
                    }

                    $issue = Issue::new('Put the variable name after the type in a @var tag.', $tag->contentSpan());
                    $swapped = count($tag->lines) === 1 ? self::typeFirst(trim($tag->content())) : null;
                    // Mago skips the tag in this order, so the swap gives the
                    // variable a type the analyzers start to trust.
                    if ($swapped !== null) {
                        $edit = TextEdit::replace($tag->contentSpan(), $swapped)->withSafety(Safety::PotentiallyUnsafe);
                        $issue = $issue->withEdit($edit);
                    }

                    $context->report($issue);
                }

                continue;
            }

            // A comment with `*/` in it is commented-out docblock text,
            // which Coder skips too.
            $text = $context->file->getText($trivia->span);
            if (
                !str_contains($text, '@var')
                || str_contains($text, '*/') && $trivia->kind !== TriviaKind::MultiLineComment
                || $this->precedesADeclaration($context->file->contents, $trivia->span->end)
            ) {
                continue;
            }

            $issue = Issue::new('Use "/** */" delimiters for an inline @var declaration.', $trivia->span)->withHelp(
                'Move the @var declaration into its own docblock. Remove the tag if it only repeats a native type hint.',
            );
            $docblock = self::asDocblock($context->file, $trivia);
            if ($docblock !== null) {
                // Mago and PHPStan ignore the comment and trust the docblock,
                // so a stale type starts to count. The fix asks first.
                $edit = TextEdit::replace($trivia->span, $docblock)->withSafety(Safety::PotentiallyUnsafe);
                $issue = $issue->withEdit($edit);
            }

            $context->report($issue);
        }
    }

    /**
     * The one-line docblock for a `//`, `#` or `/* *\/` comment that holds
     * only a `@var` tag, alone on its line and not next to other comment
     * lines, or null for any other comment.
     */
    private static function asDocblock(SourceFile $file, Trivia $trivia): ?string
    {
        $text = $file->getText($trivia->span);
        $matches = [];
        $pattern = match ($trivia->kind) {
            TriviaKind::SingleLineComment => '/^\/\/\s*@var\s+(.*?)\s*$/',
            TriviaKind::HashComment => '/^#\s*@var\s+(.*?)\s*$/',
            TriviaKind::MultiLineComment => '/^\/\*\s*@var\s+([^\n]*?)\s*\*\/$/',
            default => null,
        };
        if ($pattern === null || preg_match($pattern, $text, $matches) !== 1) {
            return null;
        }

        if (!self::aloneOnItsLine($file->contents, $trivia->span->start, $trivia->span->end)) {
            return null;
        }

        $content = $matches[1];
        $ordered = str_starts_with($content, '$') ? self::typeFirst($content) : self::typed($content);

        return $ordered === null ? null : '/** @var ' . $ordered . ' */';
    }

    /**
     * The content when it is a type and a variable name, in that order.
     */
    private static function typed(string $content): ?string
    {
        $type = DocType::leading($content);
        if ($type === null || !self::isType($type)) {
            return null;
        }

        $rest = ltrim(substr($content, strlen($type)));

        return preg_match(self::VARIABLE, $rest) === 1 ? $content : null;
    }

    /**
     * The `@var` content with the type moved before a variable name written
     * first, as in `$items array` to `array $items`, or null when no clean
     * type follows the name.
     */
    private static function typeFirst(string $content): ?string
    {
        $name = [];
        if (preg_match(self::VARIABLE, $content, $name) !== 1) {
            return null;
        }

        $after = substr($content, strlen($name[0]));
        $gap = strspn($after, characters: " \t");
        if ($gap === 0) {
            return null;
        }

        $remainder = substr($after, $gap);
        $type = DocType::leading($remainder);
        $next = ltrim(substr($remainder, $type === null ? 0 : strlen($type)));
        // A type with a space before `|`, `&` or `:` would be cut in two.
        if ($type === null || !self::isType($type) || in_array($next[0] ?? '', ['|', '&', ':'], strict: true)) {
            return null;
        }

        return rtrim($type . ' ' . $name[0] . substr($remainder, strlen($type)));
    }

    /**
     * Whether the text is a whole type: balanced, and not cut short after a
     * union, intersection, return-type or list separator.
     */
    private static function isType(string $type): bool
    {
        return (
            $type[0] !== '$'
            && DocType::balanced($type)
            && !in_array(substr($type, offset: -1), ['|', '&', ':', ','], strict: true)
        );
    }

    /**
     * Whether only whitespace shares the comment's line, and neither line
     * next to it is a `//` or `#` comment that it could belong with.
     */
    private static function aloneOnItsLine(string $contents, int $start, int $end): bool
    {
        $lineStart = strrpos(substr($contents, offset: 0, length: $start), needle: "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        if (trim(substr($contents, $lineStart, $start - $lineStart)) !== '') {
            return false;
        }

        $previousStart = $lineStart === 0
            ? false
            : strrpos(substr($contents, offset: 0, length: $lineStart - 1), needle: "\n");
        $previous = $lineStart === 0
            ? ''
            : substr(
                $contents,
                $previousStart === false ? 0 : $previousStart + 1,
                $lineStart - 1 - ($previousStart === false ? 0 : $previousStart + 1),
            );
        $lineEnd = strpos($contents, needle: "\n", offset: $end);
        $nextEnd = $lineEnd === false ? false : strpos($contents, needle: "\n", offset: $lineEnd + 1);
        $next = $lineEnd === false
            ? ''
            : substr($contents, $lineEnd + 1, ($nextEnd === false ? strlen($contents) : $nextEnd) - $lineEnd - 1);

        foreach ([$previous, $next] as $line) {
            $line = ltrim($line);
            if (str_starts_with($line, '//') || str_starts_with($line, '#')) {
                return false;
            }
        }

        return true;
    }

    private function precedesADeclaration(string $contents, int $offset): bool
    {
        return preg_match(self::DECLARATION_KEYWORDS, ltrim(substr($contents, $offset, self::LOOKAHEAD))) === 1;
    }
}
