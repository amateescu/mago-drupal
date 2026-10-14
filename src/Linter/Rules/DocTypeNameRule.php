<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\FunctionTags;
use amateescu\MagoDrupal\Internal\PropertyTags;
use amateescu\MagoDrupal\Internal\TypeNames;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Checks the type names of the `@param` and `@return` tags of a function or
 * method and of the `@var` tag of a property.
 *
 * Ports `IncorrectParamVarName` and `InvalidReturn` of
 * Drupal.Commenting.FunctionComment and the type name part of
 * `IncorrectVarType` of Drupal.Commenting.VariableComment. `TypeNames`
 * holds the names.
 */
final class DocTypeNameRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/doc-type-name',
            name: 'Doc type name',
            description: 'Reports a @param, @return or @var type spelled another way than the standard asks, such as integer for int.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method, NodeKind::Property],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind === NodeKind::Property) {
            self::checkProperty($context);

            return;
        }

        $tags = FunctionTags::of($context);
        if ($tags === null) {
            return;
        }

        foreach ($tags->params() as [$tag, $param]) {
            if ($param === null) {
                continue;
            }

            TypeNames::checkParam($context, $tag, $param->type);
        }

        $return = $tags->returnTag();
        if ($return !== null && $return->type !== null) {
            TypeNames::checkReturn($context, $return->tag, $return->type);
        }
    }

    /**
     * Checks the type of the first `@var` tag. A tag with no type, or one
     * that starts with a variable name, is `drupal/variable-comment`'s.
     */
    private static function checkProperty(LintContext $context): void
    {
        foreach (PropertyTags::of($context) ?? [] as $tag) {
            if ($tag->name !== 'var') {
                continue;
            }

            $content = $tag->content();
            if ($content === '' || PropertyTags::leadingName($content) !== null) {
                return;
            }

            [$type] = Docblocks::splitType($content);
            if ($type !== null) {
                TypeNames::checkVar($context, $tag, $type);
            }

            return;
        }
    }
}
