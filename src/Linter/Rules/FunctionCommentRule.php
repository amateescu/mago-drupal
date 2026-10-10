<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\CommentDocblock;
use amateescu\MagoDrupal\Internal\DocblockGap;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\FunctionCommentSpacing;
use amateescu\MagoDrupal\Internal\LineEnding;
use amateescu\MagoDrupal\Internal\Nodes;
use amateescu\MagoDrupal\Internal\TypeNames;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function array_reverse;
use function array_slice;
use function array_values;
use function count;
use function in_array;
use function ltrim;
use function mb_substr;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function rtrim;
use function str_contains;
use function str_starts_with;
use function stripos;
use function strlen;
use function strpos;
use function strrpos;
use function strtolower;
use function strtoupper;
use function substr;
use function trim;

/**
 * Checks that a function or method has a docblock, and that its `@param`,
 * `@return`, `@throws` and `@see` tags have the correct structure and
 * wording.
 *
 * Ports Drupal.Commenting.FunctionComment. The comparison of a docblock's
 * types against the real signature is the difficult part of this sniff,
 * and most of it is not ported. `mago analyze` already reads `@param` and
 * `@return` as authoritative types when there is no native hint. It thus
 * already reports a `void` return that returns a value, a function with no
 * `return` at all, an `@param` that names an unknown parameter, and a bare
 * tag with no type. What is left is presence, structure and prose. One
 * signature-dependent check stays: a method with partial `@param` coverage
 * that has no entry for a real parameter. Nothing else reports that.
 * `FunctionCommentSpacing` holds the whitespace checks on the tags, and
 * `TypeNames` checks the `@param` and `@return` types.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class FunctionCommentRule implements Rule
{
    /**
     * A quoted string, a block comment or a line comment. `#[` opens an
     * attribute, not a comment.
     */
    private const QUOTED_OR_COMMENT = '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|\/\*.*?\*\/|(?:\/\/|#(?!\[))[^\n]*/s';

    /**
     * A variable name right after the `@return` type, with nothing after it
     * on the line.
     */
    private const RETURN_VARIABLE = '/^([ \t]*\S+)([ \t]+\$[A-Za-z_][A-Za-z0-9_]*)[ \t]*$/';

    /**
     * A `@return` line that holds one type and one variable and nothing else.
     * A type such as `callable(int $a): int` does not match.
     */
    private const RETURN_TYPE_AND_VARIABLE = '/^[ \t]*\S+[ \t]+\$\S+[ \t]*$/';

    /**
     * The type and the variable of a `@param` line. The type is everything
     * before the first `$`, `.` or `&$`. A line with no variable gives an
     * empty second group.
     */
    private const PARAM_LINE = '/((?:(?![$.]|&(?=\$)).)*)(?:((?:\.\.\.)?(?:\$|&)[^\s]+)(?:(\s+)(.*))?)?/';

    /**
     * A `@param` line with no type, a variable and one word after it that
     * can be a type, such as `$a int` or `&$a \Drupal\node\NodeInterface`.
     */
    private const VARIABLE_THEN_TYPE = '/^([ \t]*)((?:&|\.\.\.)*\$[A-Za-z_]\w*)([ \t]+)([\w\\\\|?\[\]]+)[ \t]*$/';

    /**
     * A type that stays a single token for a class name or a union of them.
     */
    private const THROWS_TYPE = '/^[\w\\\\|]+$/';

    private string $mentionPath = '';

    /**
     * @var list<int>
     */
    private array $constructorMentions = [];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/function-comment',
            name: 'Function comment',
            description: 'Checks that a function or method has a well-formed docblock.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $anchor = Docblocks::commentAnchor($context->file, $context->node);
        $closest = Docblocks::closest($context->file, $anchor);
        if ($closest === null) {
            // A constructor with nothing above it needs no docblock. One with
            // a comment above it is checked like any other method.
            if ($this->isConstructor($context)) {
                return;
            }

            $context->report(Issue::new('The function has no docblock.', $context->node->span));

            return;
        }

        if ($closest->kind !== TriviaKind::DocBlockComment) {
            $issue = Issue::new('The function docblock must start with "/**".', $context->node->span);
            $fix = CommentDocblock::edit($context->file, $closest, $context->node, $anchor);
            $context->report($fix === null ? $issue : $issue->withEdit($fix));

            return;
        }

        $tags = Docblocks::tags($context->file, $closest->span);

        // A docblock tagged `@file` belongs to the file. Coder takes the
        // function for undocumented and checks none of the tags.
        if (self::hasTag($tags, 'file')) {
            $context->report(Issue::new('The function has no docblock.', $context->node->span));

            return;
        }

        $this->checkParamTags($context, $tags);
        $this->checkReturnTags($context, $tags);
        $this->checkThrowsTags($context, $tags);
        $this->checkSeeTags($context, $tags);
        $this->checkSpacing($context, $closest->span);
    }

    /**
     * The blank lines below the docblock and the whitespace in its tags.
     */
    private function checkSpacing(LintContext $context, Span $docblock): void
    {
        DocblockGap::checkBelow($context, $docblock, 'function');
        FunctionCommentSpacing::check($context, $docblock);
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private static function hasTag(array $tags, string $name): bool
    {
        foreach ($tags as $tag) {
            if ($tag->name === $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether the node is a method named `__construct`. PHP ignores case in
     * method names, so `__CONSTRUCT` counts too.
     */
    private function isConstructor(LintContext $context): bool
    {
        if ($context->node->kind !== NodeKind::Method) {
            return false;
        }

        // A method whose text does not have the name cannot be the
        // constructor. The rule finds the file's mentions once. Checking a
        // method against them excludes almost every method without reading
        // its nodes. The name lookup below must read the nodes.
        if ($this->mentionPath !== $context->file->path) {
            $this->mentionPath = $context->file->path;
            $this->constructorMentions = self::mentionOffsets($context->file->contents);
        }

        $span = $context->node->span;
        foreach ($this->constructorMentions as $offset) {
            if ($offset >= $span->start && $offset < $span->end) {
                return strtolower(Nodes::declaredName($context->file, $context->node) ?? '') === '__construct';
            }
        }

        return false;
    }

    /**
     * The byte offsets of every `__construct` in the file, in any case.
     *
     * @return list<int>
     */
    private static function mentionOffsets(string $contents): array
    {
        $offsets = [];
        $offset = stripos($contents, needle: '__construct');
        while ($offset !== false) {
            $offsets[] = $offset;
            $offset = stripos($contents, needle: '__construct', offset: $offset + 1);
        }

        return $offsets;
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkParamTags(LintContext $context, array $tags): void
    {
        $paramTags = [];
        foreach ($tags as $tag) {
            if ($tag->name !== 'param') {
                continue;
            }

            $paramTags[] = $tag;
            $this->checkParamTag($context, $tag, $tags);
        }

        if ($context->node->kind === NodeKind::Method && $paramTags !== []) {
            $this->checkParamCoverage($context, $paramTags);
        }
    }

    /**
     * Whether an example follows a `@param` description.
     *
     * Drupal writes the example inside the description. It is either
     * indented with the text, or at the star column, where it parses as a
     * tag of its own. Coder skips the same markers when it reads a param
     * comment. With both spellings, the check for terminal punctuation thus
     * applies to the description, not to the example's last line. Coder
     * skips `@link` the same way, but a link is not an example. The
     * description before it is prose and keeps its check.
     *
     * @param list<DocblockTag> $tags
     */
    private function precedesExample(array $tags, DocblockTag $tag): bool
    {
        if ($this->endsInExample($tag)) {
            return true;
        }

        $index = Docblocks::indexOf($tags, $tag);
        if ($index === null) {
            return false;
        }

        $next = $tags[$index + 1] ?? null;

        return $next !== null && in_array($next->name, Docblocks::EXAMPLE_TAGS, strict: true);
    }

    /**
     * @param list<DocblockTag> $tags Every tag in the docblock. The rule
     *   uses them to find whether an example follows the description.
     */
    private function checkParamTag(LintContext $context, DocblockTag $tag, array $tags): void
    {
        $content = $tag->content();
        $matches = [];
        // The `&` of a reference and the `...` of a variadic parameter belong
        // to the variable, as in Coder. `@param &$a` has no type.
        if (preg_match('/(?:&|\.\.\.)*\$[A-Za-z_][A-Za-z0-9_]*/', $content, $matches) !== 1) {
            if (trim($content) !== '') {
                $context->report(Issue::new('The @param tag has no $variable name.', $tag->contentSpan()));
            }

            return;
        }

        $variable = $matches[0];
        $offset = strpos($content, $variable);
        $offset = $offset === false ? 0 : $offset;
        $type = trim(substr($content, offset: 0, length: $offset));
        $rest = substr($content, $offset + strlen($variable));

        if ($type === '') {
            $context->report(self::missingType($tag));
        }

        TypeNames::checkParam($context, $tag, $type);
        $this->checkParamTypeSpaces($context, $tag);

        if (str_starts_with($rest, '.')) {
            $issue = Issue::new('Do not put a period after the @param variable name.', $tag->contentSpan());
            $line = $tag->lines[0];
            // One period after the name on the tag's line. `$names...` and
            // `$a.b` are left alone.
            $position = strpos($line->text, $variable . '.');
            $after = $position === false ? null : substr($line->text, $position + strlen($variable) + 1, length: 1);
            if ($position !== false && in_array($after, ['', ' ', "\t"], strict: true)) {
                $at = $line->offset + $position + strlen($variable);
                $issue = $issue->withEdit(TextEdit::delete(new Span($at, $at + 1)));
            }

            $context->report($issue);
        }

        $description = $type === '' ? self::untypedDescription($tag, $rest) : ltrim($rest, characters: ". \t");
        if ($description === '') {
            $context->report(Issue::new('The @param tag has no description.', $tag->contentSpan()));

            return;
        }

        // Coder checks the wording only when the tag has a type.
        if ($type !== '') {
            $this->checkParamWording($context, $tag, $tags, $variable, $description);
        }
    }

    /**
     * Checks that a `@param` description has a capital letter and ends with
     * terminal punctuation.
     *
     * @param list<DocblockTag> $tags
     */
    private function checkParamWording(
        LintContext $context,
        DocblockTag $tag,
        array $tags,
        string $variable,
        string $description,
    ): void {
        // Coder wants an upper-case letter anywhere in the first line of the
        // description, so `lower Case.` passes and `_lower.` does not.
        if (preg_match('/\p{Lu}/u', self::firstLineBelow($tag, $variable) ?? $description) === 0) {
            $context->report(Issue::new(
                'The param description must start with a capital letter.',
                $tag->contentSpan(),
            ));
        }

        // A description with an example after it ends on the example, not on
        // a sentence. The ported sniff exempts that.
        if (!$this->precedesExample($tags, $tag)) {
            $this->checkProseEnd(
                $context,
                $description,
                $tag->contentSpan(),
                'param description',
                self::fullStop($tag),
            );
        }
    }

    /**
     * The issue for a `@param` with no type. phpcbf moves a single word
     * after the variable in front of it, as the type. The fix does the same
     * when the word is made of type characters, so a one-word sentence such
     * as `Done.` stays where it is.
     */
    private static function missingType(DocblockTag $tag): Issue
    {
        $issue = Issue::new('The @param tag has no type.', $tag->contentSpan());
        $line = $tag->lines[0];
        $matches = [];
        if (preg_match(self::VARIABLE_THEN_TYPE, $line->text, $matches) !== 1) {
            return $issue;
        }

        $start = $line->offset + strlen($matches[1]);
        $end = $start + strlen($matches[2]) + strlen($matches[3]) + strlen($matches[4]);

        return $issue->withEdit(TextEdit::replace(new Span($start, $end), $matches[4] . ' ' . $matches[2]));
    }

    /**
     * The description of a `@param` with no type. Coder reads a single word
     * after the variable on the tag's line as the type. In that case the
     * description is the lines below.
     */
    private static function untypedDescription(DocblockTag $tag, string $rest): string
    {
        $matches = [];
        preg_match(self::PARAM_LINE, $tag->lines[0]->text, $matches);
        $after = trim($matches[4] ?? '');
        if ($after !== '' && preg_match('/\s/', $after) !== 1) {
            return self::textBelow($tag);
        }

        return ltrim($rest, characters: ". \t");
    }

    /**
     * The first line of text below the line that holds the variable, or
     * null when there is none. The variable is on the tag's own line unless
     * the type spans several lines.
     */
    private static function firstLineBelow(DocblockTag $tag, string $variable): ?string
    {
        $below = false;
        foreach ($tag->lines as $line) {
            $text = trim($line->text);
            if ($below && $text !== '') {
                return $text;
            }

            $below = $below || str_contains($line->text, $variable);
        }

        return null;
    }

    /**
     * Reports a `@param` type that holds whitespace. Coder splits the tag's
     * own line at the first `$`, `.` or `&$`, so the type is what comes
     * before that. It skips a line with no type or no variable, and a type
     * that holds a bracket, since those are PHPStan types with spaces in
     * them.
     */
    private function checkParamTypeSpaces(LintContext $context, DocblockTag $tag): void
    {
        $matches = [];
        preg_match(self::PARAM_LINE, $tag->lines[0]->text, $matches);
        $type = trim($matches[1] ?? '');
        if ($type === '' || ($matches[2] ?? '') === '') {
            return;
        }

        if (preg_match('/\s/', $type) === 1 && preg_match('/[<\[{(]/', $type) !== 1) {
            $context->report(Issue::new("The @param type \"{$type}\" must not contain spaces.", $tag->nameSpan));
        }
    }

    /**
     * @param list<DocblockTag> $paramTags
     */
    private function checkParamCoverage(LintContext $context, array $paramTags): void
    {
        $documented = [];
        foreach ($paramTags as $tag) {
            $matches = [];
            if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*/', $tag->content(), $matches) === 1) {
                $documented[$matches[0]] = true;
            }
        }

        foreach ($this->realParameters($context) as $name) {
            if ($documented[$name] ?? false) {
                continue;
            }

            $context->report(Issue::new("The docblock has no @param tag for {$name}.", $context->node->span));
        }
    }

    /**
     * @return list<string>
     */
    private function realParameters(LintContext $context): array
    {
        foreach ($context->file->getChildren($context->node) as $child) {
            if ($child->kind !== NodeKind::FunctionLikeParameterList) {
                continue;
            }

            // The rule reads the variables off the list's text, not its nodes.
            // The nodes take several reads per parameter. A type, an
            // attribute or a default value holds no variable. The only other
            // places for a `$` are a quoted string or a comment. The rule
            // blanks those first.
            $text =
                preg_replace(self::QUOTED_OR_COMMENT, replacement: '', subject: $context->file->getText($child)) ?? '';
            $matches = [];
            preg_match_all('/\$[A-Za-z_][A-Za-z0-9_]*/', $text, $matches);

            return array_values($matches[0]);
        }

        return [];
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkReturnTags(LintContext $context, array $tags): void
    {
        $returnTags = [];
        foreach ($tags as $tag) {
            if ($tag->name !== 'return') {
                continue;
            }

            $returnTags[] = $tag;
        }

        if (count($returnTags) > 1) {
            $context->report(Issue::new('Use only one @return tag.', $returnTags[1]->nameSpan));
        }

        if ($returnTags === []) {
            return;
        }

        $tag = $returnTags[0];
        if (count($returnTags) === 1 && $this->reportMissingReturnType($context, $tags, $tag)) {
            return;
        }

        [$type, $rest] = Docblocks::splitType($tag->content());
        if ($type === null) {
            return;
        }

        TypeNames::checkReturn($context, $tag, $type);

        // Only the tag's own line can hold a variable name. A description
        // below it may start with one, as in `$this.`.
        $line = $tag->lines[0];
        if (preg_match(self::RETURN_TYPE_AND_VARIABLE, $line->text) === 1) {
            $issue = Issue::new('Do not put a variable name after the @return type.', $tag->contentSpan());
            $name = [];
            // The name goes when the line holds only the type and it, and a
            // description follows. Without one, the name is all the author
            // wrote about the value.
            if (
                count($tag->lines) > 1
                && trim($tag->lines[1]->text) !== ''
                && preg_match(self::RETURN_VARIABLE, $line->text, $name) === 1
            ) {
                $start = $line->offset + strlen($name[1]);
                $issue = $issue->withEdit(TextEdit::delete(new Span($start, $start + strlen($name[2]))));
            }

            $context->report($issue);

            return;
        }

        if (count($returnTags) === 1) {
            $this->checkReturnTypeSpaces($context, $tags, $tag);
        }

        // `@return void` needs no description either.
        if ($rest === '' && !in_array($type, ['$this', 'static'], strict: true) && strtolower($type) !== 'void') {
            $context->report(Issue::new('The @return tag has no description.', $tag->contentSpan()));
        }
    }

    /**
     * Reports a `@return` with nothing after it on its own line. Coder reads
     * the type from that line only, so text below the tag is a description
     * and not a type. A `@return` that is the last tag with nothing under it
     * is left out, since Mago's `valid-docblock` already reports it.
     *
     * @param list<DocblockTag> $tags
     */
    private function reportMissingReturnType(LintContext $context, array $tags, DocblockTag $tag): bool
    {
        if (trim($tag->lines[0]->text) !== '') {
            return false;
        }

        $last = $tags[count($tags) - 1] === $tag;
        if (!$last || self::textBelow($tag) !== '') {
            $context->report(Issue::new('The @return tag has no type.', $tag->nameSpan));
        }

        return true;
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
    private function checkReturnTypeSpaces(LintContext $context, array $tags, DocblockTag $tag): void
    {
        $text = trim($tag->lines[0]->text);
        if (!str_contains($text, ' ') || preg_match('/[<\[{(]/', $text) === 1) {
            return;
        }

        $below = self::textBelow($tag);
        $index = Docblocks::indexOf($tags, $tag) ?? count($tags);
        // Example markers right after the tag belong to its description.
        while ($below === '' && in_array(($tags[++$index] ?? null)?->name, Docblocks::EXAMPLE_TAGS, strict: true)) {
            $below = self::textBelow($tags[$index]);
        }

        if ($below !== '') {
            $context->report(Issue::new("The @return type \"{$text}\" must not contain spaces.", $tag->nameSpan));
        }
    }

    /**
     * The text on the lines below a tag's own line.
     */
    private static function textBelow(DocblockTag $tag): string
    {
        $text = '';
        foreach (array_slice($tag->lines, offset: 1) as $line) {
            $text .= trim($line->text);
        }

        return $text;
    }

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkThrowsTags(LintContext $context, array $tags): void
    {
        foreach ($tags as $tag) {
            if ($tag->name !== 'throws') {
                continue;
            }

            $this->checkThrowsComment($context, $tag);

            [$type, $rest] = Docblocks::splitType($tag->content());
            if ($type === null || $rest === '') {
                // mago analyze already reports an empty @throws as a malformed
                // docblock. A description is not necessary for a type-only
                // @throws.
                continue;
            }

            $this->checkProse($context, $rest, $tag->contentSpan(), '@throws description');
        }
    }

    /**
     * Reports a `@throws` that has text after its type on the tag's line and
     * nothing below. The description goes on the next line. The fix moves it
     * there, three spaces in from the star, when the tag is a type that has
     * no `*\/` after it.
     *
     * Coder counts the words on the tag's line and so also reports a type
     * such as `\Foo2Bar` or `\A|\B` that has no text. Those have no
     * description to move, and the rule leaves them out.
     */
    private function checkThrowsComment(LintContext $context, DocblockTag $tag): void
    {
        $line = $tag->lines[0];
        $first = ltrim($line->text);
        [$type, $description] = Docblocks::splitType($first);
        if ($type === null || $description === '' || self::textBelow($tag) !== '') {
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

    /**
     * @param list<DocblockTag> $tags
     */
    private function checkSeeTags(LintContext $context, array $tags): void
    {
        // A `@see` after `@deprecated` holds the change-record url, whose
        // trailing periods `drupal/deprecated-tag` removes. A second edit
        // there would overlap that one.
        $deprecatedAt = null;
        foreach ($tags as $index => $tag) {
            if ($tag->name !== 'deprecated') {
                continue;
            }

            $deprecatedAt = $index;
            break;
        }

        foreach ($tags as $index => $tag) {
            if ($tag->name !== 'see') {
                continue;
            }

            $deprecated = $deprecatedAt !== null && $index > $deprecatedAt;

            // The reference is the tag's own line, as Coder reads it. The
            // lines below it are a description, which may end in a period.
            // A tag with its reference on the next line counts as empty.
            $line = $tag->lines[0];
            $reference = rtrim($line->text);
            if (trim($reference) === '') {
                $context->report(Issue::new('The @see tag must have content.', $tag->nameSpan));

                continue;
            }

            [, $rest] = Docblocks::splitType(ltrim($reference));
            if ($rest !== '') {
                $context->report(Issue::new(
                    'The @see tag must have only a reference, with no other text.',
                    $tag->contentSpan(),
                ));
            }

            $kept = rtrim($reference, characters: '.!?');
            if ($kept === $reference) {
                continue;
            }

            $issue = Issue::new('Do not end the @see reference with punctuation.', $tag->contentSpan());
            if ($rest === '' && trim($kept) !== '' && !$deprecated) {
                $issue = $issue->withEdit(TextEdit::delete(
                    new Span($line->offset + strlen($kept), $line->offset + strlen($reference)),
                ));
            }

            $context->report($issue);
        }
    }

    /**
     * Whether a tag's last line is a `@code` example, not prose.
     */
    private function endsInExample(DocblockTag $tag): bool
    {
        foreach (array_reverse($tag->lines) as $line) {
            $text = trim($line->text);
            if ($text === '') {
                continue;
            }

            return str_starts_with($text, '@code') || str_starts_with($text, '@endcode');
        }

        return false;
    }

    /**
     * Checks that free-form text starts with a capital letter and ends with
     * terminal punctuation.
     */
    private function checkProse(LintContext $context, string $text, Span $span, string $label): void
    {
        $this->checkProseStart($context, $text, $span, $label);
        $this->checkProseEnd($context, $text, $span, $label);
    }

    /**
     * Checks that free-form text starts with a capital letter. Coder runs
     * `strtoupper()` on the first byte, so only a lower-case ASCII letter is
     * reported.
     */
    private function checkProseStart(LintContext $context, string $text, Span $span, string $label): void
    {
        $first = substr($text, offset: 0, length: 1);
        if ($first !== strtoupper($first)) {
            $context->report(Issue::new("The {$label} must start with a capital letter.", $span));
        }
    }

    /**
     * Checks that free-form text ends with terminal punctuation.
     */
    private function checkProseEnd(
        LintContext $context,
        string $text,
        Span $span,
        string $label,
        ?TextEdit $fix = null,
    ): void {
        $last = mb_substr(rtrim($text), -1);
        if (in_array($last, ['.', '!', '?', ')'], strict: true)) {
            return;
        }

        $issue = Issue::new("The {$label} must end with terminal punctuation.", $span);
        $context->report($fix === null ? $issue : $issue->withEdit($fix));
    }

    /**
     * The edit that adds a full stop to a tag's last line, or null when that
     * line ends in something a period would spoil: a url, a tag such as an
     * indented `@see`, or a colon, comma or semicolon that announces more.
     */
    private static function fullStop(DocblockTag $tag): ?TextEdit
    {
        foreach (array_reverse($tag->lines) as $line) {
            $text = rtrim($line->text);
            if ($text === '') {
                continue;
            }

            $trimmed = ltrim($text);
            if (
                str_starts_with($trimmed, '@')
                || preg_match('~https?://\S+$~', $text) === 1
                || preg_match('/[:,;]$|\.["\']$/', $text) === 1
            ) {
                return null;
            }

            return TextEdit::insert($line->offset + strlen($text), '.');
        }

        return null;
    }
}
