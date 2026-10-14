<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\FunctionTags;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function preg_match;
use function strlen;
use function trim;

/**
 * Checks that the `@param` and `@return` tags of a function or method have
 * a type, and a `@param` a variable name.
 *
 * Ports `MissingParamName`, `MissingParamType`, `MissingReturnType` and
 * `DuplicateReturn` of Drupal.Commenting.FunctionComment.
 */
final class DocTagTypesRule implements Rule
{
    /**
     * A `@param` line with no type, a variable and one word after it that
     * can be a type, such as `$a int` or `&$a \Drupal\node\NodeInterface`.
     */
    private const VARIABLE_THEN_TYPE = '/^([ \t]*)((?:&|\.\.\.)*\$[A-Za-z_]\w*)([ \t]+)([\w\\\\|?\[\]]+)[ \t]*$/';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/doc-tag-types',
            name: 'Doc tag types',
            description: 'Reports a @param with no type or variable name, a @return with no type, and a second @return.',
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
                if (trim($tag->content()) !== '') {
                    $context->report(Issue::new('The @param tag has no $variable name.', $tag->contentSpan()));
                }

                continue;
            }

            if ($param->type === '') {
                $context->report(self::missingType($tag));
            }
        }

        $returns = $tags->named('return');
        if (count($returns) > 1) {
            $context->report(Issue::new('Use only one @return tag.', $returns[1]->nameSpan));
        }

        // Coder reads the type from the tag's own line only, so text below
        // the tag is a description and not a type. A `@return` that is the
        // last tag with nothing under it is left out, since Mago's
        // `valid-docblock` already reports it.
        $return = $tags->returnTag();
        if ($return === null || !$return->isBare()) {
            return;
        }

        $last = $tags->tags[count($tags->tags) - 1] === $return->tag;
        if (!$last || $return->tag->textBelow() !== '') {
            $context->report(Issue::new('The @return tag has no type.', $return->tag->nameSpan));
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
}
