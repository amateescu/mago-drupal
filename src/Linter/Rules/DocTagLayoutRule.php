<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\FunctionCommentSpacing;
use amateescu\MagoDrupal\Internal\FunctionTags;
use amateescu\MagoDrupal\Internal\LineEnding;
use amateescu\MagoDrupal\Internal\ParamTag;
use amateescu\MagoDrupal\Internal\ReturnTag;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

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
use function substr;
use function trim;

/**
 * Checks the layout of the `@param`, `@return` and `@throws` tags of a
 * function or method.
 *
 * Ports the layout codes of Drupal.Commenting.FunctionComment: where a
 * description goes and how it is indented, the space after a `@param` type,
 * a period after the variable, a variable after the `@return` type, and
 * spaces in a type. `FunctionCommentSpacing` holds the whitespace checks.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocTagLayoutRule implements Rule
{
    /**
     * A variable name right after the `@return` type, with nothing after it
     * on the line.
     */
    private const RETURN_VARIABLE = '/^([ \t]*\S+)([ \t]+\$[A-Za-z_][A-Za-z0-9_]*)[ \t]*$/';

    /**
     * A type that stays a single token for a class name or a union of them.
     */
    private const THROWS_TYPE = '/^[\w\\\\|]+$/';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/doc-tag-layout',
            name: 'Doc tag layout',
            description: 'Reports a @param, @return or @throws tag whose description is on the wrong line or has the wrong indent, or whose spacing is wrong.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $tags = FunctionTags::of($context);
        if ($tags === null) {
            return;
        }

        foreach ($tags->params() as [$tag, $param]) {
            if ($param === null) {
                continue;
            }

            self::checkParamTypeSpaces($context, $tag);
            self::checkParamNameDot($context, $param);
        }

        $return = $tags->returnTag();
        if ($return !== null && $return->type !== null) {
            $this->checkReturn($context, $tags->tags, $return);
        }

        foreach ($tags->named('throws') as $tag) {
            self::checkThrowsComment($context, $tag);
        }

        FunctionCommentSpacing::check($context, $tags->docblock);
    }

    /**
     * Reports a `@param` type that holds whitespace. Coder splits the tag's
     * own line at the first `$`, `.` or `&$`, so the type is what comes
     * before that. It skips a line with no type or no variable, and a type
     * that holds a bracket, since those are PHPStan types with spaces in
     * them.
     */
    private static function checkParamTypeSpaces(LintContext $context, DocblockTag $tag): void
    {
        $matches = [];
        preg_match(ParamTag::LINE, $tag->lines[0]->text, $matches);
        $type = trim($matches[1] ?? '');
        if ($type === '' || ($matches[2] ?? '') === '') {
            return;
        }

        if (preg_match('/\s/', $type) === 1 && preg_match('/[<\[{(]/', $type) !== 1) {
            $context->report(Issue::new("The @param type \"{$type}\" must not contain spaces.", $tag->nameSpan));
        }
    }

    /**
     * Reports a period after the `@param` variable. The fix removes one
     * period after the name on the tag's line. `$names...` and `$a.b` are
     * left alone.
     */
    private static function checkParamNameDot(LintContext $context, ParamTag $param): void
    {
        if (!str_starts_with($param->rest, '.')) {
            return;
        }

        $tag = $param->tag;
        $variable = $param->variable;
        $issue = Issue::new('Do not put a period after the @param variable name.', $tag->contentSpan());
        $line = $tag->lines[0];
        $position = strpos($line->text, $variable . '.');
        $after = $position === false ? null : substr($line->text, $position + strlen($variable) + 1, length: 1);
        if ($position !== false && in_array($after, ['', ' ', "\t"], strict: true)) {
            $at = $line->offset + $position + strlen($variable);
            $issue = $issue->withEdit(TextEdit::delete(new Span($at, $at + 1)));
        }

        $context->report($issue);
    }

    /**
     * Reports a variable name after the `@return` type, and else a type
     * with a space in it.
     *
     * @param list<DocblockTag> $tags
     */
    private function checkReturn(LintContext $context, array $tags, ReturnTag $return): void
    {
        $tag = $return->tag;
        if (!$return->namesVariable()) {
            if ($return->count === 1) {
                self::checkReturnTypeSpaces($context, $tags, $tag);
            }

            return;
        }

        $issue = Issue::new('Do not put a variable name after the @return type.', $tag->contentSpan());
        $line = $tag->lines[0];
        $name = [];
        // The name goes when the line holds only the type and it, and a
        // description follows. Without one, the name is all the author wrote
        // about the value.
        if (
            count($tag->lines) > 1
            && trim($tag->lines[1]->text) !== ''
            && preg_match(self::RETURN_VARIABLE, $line->text, $name) === 1
        ) {
            $start = $line->offset + strlen($name[1]);
            $issue = $issue->withEdit(TextEdit::delete(new Span($start, $start + strlen($name[2]))));
        }

        $context->report($issue);
    }

    /**
     * Reports a `@return` type with a space in it. Coder takes the whole
     * text on the tag's line as the type, so a description written there
     * shows up as part of it. It reports only when a description follows
     * below, since a missing description is a different problem, and skips a
     * type with a bracket.
     *
     * @param list<DocblockTag> $tags
     */
    private static function checkReturnTypeSpaces(LintContext $context, array $tags, DocblockTag $tag): void
    {
        $text = trim($tag->lines[0]->text);
        if (!str_contains($text, ' ') || preg_match('/[<\[{(]/', $text) === 1) {
            return;
        }

        $below = $tag->textBelow();
        $index = Docblocks::indexOf($tags, $tag) ?? count($tags);
        // Example markers right after the tag belong to its description.
        while ($below === '' && in_array(($tags[++$index] ?? null)?->name, Docblocks::EXAMPLE_TAGS, strict: true)) {
            $below = $tags[$index]->textBelow();
        }

        if ($below !== '') {
            $context->report(Issue::new("The @return type \"{$text}\" must not contain spaces.", $tag->nameSpan));
        }
    }

    /**
     * Reports a `@throws` that has text after its type on the tag's line and
     * nothing below. The description goes on the next line. The fix moves it
     * there, three spaces in from the star, when the tag is a type that
     * doesn't have `*\/` after it.
     *
     * Coder counts the words on the tag's line and so also reports a type
     * such as `\Foo2Bar` or `\A|\B` that doesn't have any text. Those don't
     * have a description to move, and the rule leaves them out.
     */
    private static function checkThrowsComment(LintContext $context, DocblockTag $tag): void
    {
        $line = $tag->lines[0];
        $first = ltrim($line->text);
        [$type, $description] = Docblocks::splitType($first);
        if ($type === null || $description === '' || $tag->textBelow() !== '') {
            return;
        }

        $issue = Issue::new('The @throws description must be on the line below the tag.', $tag->nameSpan);
        $contents = $context->file->contents;
        $end = $line->offset + strlen(rtrim($line->text));
        $before = substr($contents, offset: 0, length: $tag->nameSpan->start);
        $star = [];
        $prefix = substr($before, (int) strrpos($before, needle: "\n") + 1);
        if (
            preg_match(self::THROWS_TYPE, $type) === 1
            && preg_match('/^([ \t]*\*)[ \t]*$/', $prefix, $star) === 1
            && preg_match('/\G[ \t]*\*\//', $contents, offset: $end) !== 1
        ) {
            $start = $line->offset + strlen($line->text) - strlen($first) + strlen($type);
            $break = LineEnding::of($contents) . $star[1] . '   ';
            $issue = $issue->withEdit(TextEdit::replace(new Span($start, $end), $break . $description));
        }

        $context->report($issue);
    }
}
