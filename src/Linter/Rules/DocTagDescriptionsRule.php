<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\FunctionTags;
use amateescu\MagoDrupal\Internal\ReturnTag;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function array_values;
use function in_array;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function strtolower;

/**
 * Checks that the `@param` and `@return` tags of a function or method have
 * a description, and that a method with `@param` tags documents every
 * parameter.
 *
 * Ports `MissingParamComment`, `MissingReturnComment` and
 * `ParamMissingDefinition` of Drupal.Commenting.FunctionComment. The last
 * one is the only check here that reads the signature. Nothing else reports
 * a method with partial `@param` coverage that has no entry for a real
 * parameter.
 *
 * @mago-expect lint:cyclomatic-complexity
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
            description: 'Reports a @param or @return with no description, and a method parameter with no @param when others have one.',
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

        $paramTags = [];
        foreach ($tags->params() as [$tag, $param]) {
            $paramTags[] = $tag;
            if ($param !== null && $param->description === '') {
                $context->report(Issue::new('The @param tag has no description.', $tag->contentSpan()));
            }
        }

        if ($context->node->kind === NodeKind::Method && $paramTags !== []) {
            $this->checkParamCoverage($context, $paramTags);
        }

        $return = $tags->returnTag();
        if ($return !== null && self::lacksDescription($return)) {
            $context->report(Issue::new('The @return tag has no description.', $return->tag->contentSpan()));
        }
    }

    /**
     * Whether a `@return` with a type has no description. A variable name
     * after the type is `drupal/doc-tag-layout`'s, and `@return void`,
     * `@return static` and `@return $this` need no description.
     */
    private static function lacksDescription(ReturnTag $return): bool
    {
        $type = $return->type;

        return (
            $type !== null
            && !$return->namesVariable()
            && $return->rest === ''
            && !in_array($type, ['$this', 'static'], strict: true)
            && strtolower($type) !== 'void'
        );
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
}
