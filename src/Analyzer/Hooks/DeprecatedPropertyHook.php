<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use amateescu\MagoDrupal\Internal\FileMembers;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function ltrim;
use function strtolower;

/**
 * Reports reads and writes of a deprecated property.
 *
 * Mago has no check for deprecated properties. The property's name is
 * checked against the ones marked on disk before anything is resolved. On
 * `$this` the class comes from the file and needs no type. On anything else
 * the receiver's type is fetched, and a repeat access Mago does not analyze
 * again goes unreported.
 *
 * @internal
 */
final class DeprecatedPropertyHook implements NodeAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/deprecated-property`.
     */
    public const CODE = 'deprecated-property';

    public function __construct(
        private readonly DeprecatedSymbols $symbols,
        private readonly DeprecatedUse $use,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::PropertyAccess, NodeKind::NullSafePropertyAccess, NodeKind::StaticPropertyAccess];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::SourceText, FileAnalysisRequirement::TargetSubtree];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $source = $context->source;
        $children = $source->getChildren($context->node);
        if (count($children) !== 2) {
            return;
        }

        [$object, $selector] = $children;
        $property = ltrim($source->getText($selector), characters: '$');
        if (!$this->symbols->hasPropertyName($property)) {
            return;
        }

        foreach (self::classes($context, $object) as $class) {
            foreach (DeprecatedUse::lineage($context->codebase, $class) as $ancestor) {
                $text = $this->symbols->property($ancestor, $property);
                if ($text === null) {
                    continue;
                }

                $this->use->report(
                    $context,
                    self::CODE,
                    $context->node->span,
                    'Access to deprecated property `'
                    . DeprecatedUse::originalName($context->codebase, $ancestor)
                    . "::\${$property}`.",
                    $text,
                );

                return;
            }
        }
    }

    /**
     * The classes the property is read from.
     *
     * @return list<string>
     */
    private static function classes(NodeAnalysisContext $context, Node $object): array
    {
        $source = $context->source;
        if ($context->node->kind === NodeKind::StaticPropertyAccess) {
            $class = DeprecatedUse::className($context, $object);

            return $class === null ? [] : [$class];
        }

        if (strtolower($source->getText($object)) === '$this') {
            $class = FileMembers::of($source)->classAt($context->codebase, $source, $object->span);

            return $class === null ? [] : [$class->name];
        }

        return Types::names($context->analysis->getExpressionType($object->span));
    }
}
