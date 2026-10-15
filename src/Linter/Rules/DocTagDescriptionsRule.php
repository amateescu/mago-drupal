<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\FunctionTags;
use amateescu\MagoDrupal\Internal\ParamTag;
use amateescu\MagoDrupal\Internal\ReturnTag;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_ends_with;
use function strtolower;
use function trim;

use const PREG_SET_ORDER;

/**
 * Checks that the `@param` and `@return` tags of a function or method have
 * a description, that the `@param` tags follow the parameters, and that a
 * method with `@param` tags documents every parameter.
 *
 * Ports `MissingParamComment`, `MissingReturnComment`, `ParamMissingDefinition`
 * and part of `ParamNameNoMatch` of Drupal.Commenting.FunctionComment. The last
 * two read the signature. Nothing else reports a method with partial `@param`
 * coverage that doesn't have an entry for a real parameter, or a `@param` tag
 * that repeats an earlier one or is out of order.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DocTagDescriptionsRule implements Rule
{
    /**
     * A quoted string, a block comment or a line comment. `#[` opens an
     * attribute, not a comment.
     */
    private const QUOTED_OR_COMMENT = '/\'(?:[^\'\\\\]|\\\\.)*\'|"(?:[^"\\\\]|\\\\.)*"|\/\*.*?\*\/|(?:\/\/|#(?!\[))[^\n]*/s';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/doc-tag-descriptions',
            name: 'Doc tag descriptions',
            description: 'Reports a @param or @return with no description, a @param that repeats one or is out of order, and a method parameter with no @param when others have one.',
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

        $params = $tags->params();
        $paramTags = [];
        foreach ($params as [$tag, $param]) {
            $paramTags[] = $tag;
            if ($param !== null && $param->description === '') {
                $context->report(Issue::new('The @param tag has no description.', $tag->contentSpan()));
            }
        }

        if ($paramTags !== []) {
            $parameters = $this->realParameters($context);
            self::checkParamOrder($context, $paramTags, $parameters);
            if ($context->node->kind === NodeKind::Method) {
                $this->checkParamCoverage($context, $paramTags, $parameters);
            }
        }

        // Coder takes the lines below the tag as the description. Text after
        // the type on the tag's own line is part of the type for Coder.
        $return = $tags->returnTag();
        if ($return !== null && self::needsDescription($return) && $return->tag->textBelow() === '') {
            $context->report(Issue::new(
                $return->rest === ''
                    ? 'The @return tag has no description.'
                    : 'The @return description must be on the line below the tag.',
                $return->tag->contentSpan(),
            ));
        }
    }

    /**
     * Whether a `@return` with a type needs a description. A variable name
     * after the type is `drupal/doc-tag-layout`'s, and `@return void`,
     * `@return static` and `@return $this` need no description.
     */
    private static function needsDescription(ReturnTag $return): bool
    {
        $type = $return->type;

        return (
            $type !== null
            && !$return->namesVariable()
            && !in_array($type, ['$this', 'static'], strict: true)
            && strtolower($type) !== 'void'
        );
    }

    /**
     * Reports a typed `@param` tag for a real parameter that repeats an
     * earlier tag or comes out of order.
     *
     * This is Coder's scan. Each tag looks for its parameter from the one after
     * the last match onward. A tag that doesn't find one is reported, and the
     * scan goes back to the tag's own position. As in Coder, a tag with no type
     * or no variable takes a position and is skipped, and a variable that ends
     * in a period doesn't take one. A name that isn't a parameter is the
     * analyzer's `invalid-param-tag`.
     *
     * @param list<DocblockTag> $paramTags
     * @param list<list<string>> $parameters
     */
    private static function checkParamOrder(LintContext $context, array $paramTags, array $parameters): void
    {
        $count = count($parameters);
        $position = 0;
        $check = 0;
        $seen = [];
        foreach ($paramTags as $tag) {
            $matches = [];
            preg_match(ParamTag::LINE, $tag->lines[0]->text, $matches);
            $variable = $matches[2] ?? '';
            if (str_ends_with($variable, '.')) {
                continue;
            }

            $index = $position;
            $position++;
            if ($variable === '') {
                continue;
            }

            $repeats = $seen[$variable] ?? false;
            $seen[$variable] = true;
            if (trim($matches[1] ?? '') === '') {
                continue;
            }

            while ($check < $count && !in_array($variable, $parameters[$check], strict: true)) {
                $check++;
            }

            if ($check < $count) {
                $check++;
                continue;
            }

            if (self::isParameter($variable, $parameters)) {
                $context->report(Issue::new(
                    $repeats
                        ? "The @param tag for {$variable} repeats an earlier tag."
                        : "The @param tag for {$variable} is out of order.",
                    $tag->contentSpan(),
                ));
            }

            $check = $check >= $index ? $index : $check + 1;
        }
    }

    /**
     * @param list<list<string>> $parameters
     */
    private static function isParameter(string $variable, array $parameters): bool
    {
        foreach ($parameters as $names) {
            if (in_array($variable, $names, strict: true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<DocblockTag> $paramTags
     * @param list<list<string>> $parameters
     */
    private function checkParamCoverage(LintContext $context, array $paramTags, array $parameters): void
    {
        $documented = [];
        foreach ($paramTags as $tag) {
            $matches = [];
            if (preg_match('/\$[A-Za-z_][A-Za-z0-9_]*/', $tag->content(), $matches) === 1) {
                $documented[$matches[0]] = true;
            }
        }

        foreach ($parameters as [$name]) {
            if ($documented[$name] ?? false) {
                continue;
            }

            $context->report(Issue::new("The docblock has no @param tag for {$name}.", $context->node->span));
        }
    }

    /**
     * Returns the parameters in order. Each entry lists the names that a
     * `@param` tag can give the parameter, as Coder matches them. The
     * variable comes first, then `&$a` for a reference and `...$a` for a
     * variadic parameter.
     *
     * @return list<list<string>>
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
            preg_match_all('/(&\s*)?(\.\.\.\s*)?(\$[A-Za-z_][A-Za-z0-9_]*)/', $text, $matches, flags: PREG_SET_ORDER);
            $parameters = [];
            foreach ($matches as $match) {
                $names = [$match[3]];
                if ($match[1] !== '') {
                    $names[] = '&' . $match[3];
                }

                if ($match[2] !== '') {
                    $names[] = '...' . $match[3];
                }

                $parameters[] = $names;
            }

            return $parameters;
        }

        return [];
    }
}
