<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\DeprecatedSymbols;
use amateescu\MagoDrupal\Internal\FileMembers;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function max;
use function preg_match;
use function rtrim;
use function strlen;
use function strtolower;
use function substr;

/**
 * Reports reads and writes of a deprecated property.
 *
 * Mago has no check for deprecated properties. The property's name is read
 * off the end of the access and checked against the ones marked on disk
 * before anything is resolved. On `$this` the class comes from the file and
 * needs no type. On anything else the receiver's type is fetched, and a
 * repeat access Mago does not analyze again goes unreported.
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

    /**
     * How much of the end of an access to read for its property name.
     */
    private const TAIL = 128;

    /**
     * The operator and the property name that end an access.
     */
    private const SELECTOR =
        '/'
            . DeprecatedUse::GAP
            . '(\?->|->|::'
            . DeprecatedUse::GAP
            . '\$)'
            . DeprecatedUse::GAP
            . '([A-Za-z_]\w*)\z/';

    private const CLASS_NAME = '/\A\\\\?[A-Za-z_][\w\\\\]*\z/';

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
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $span = $context->node->span;
        $contents = $context->source->contents;
        $from = max($span->start, $span->end - self::TAIL);
        $tail = substr($contents, $from, $span->end - $from);
        $matches = [];
        if (preg_match(self::SELECTOR, $tail, $matches) !== 1 || !$this->symbols->hasPropertyName($matches[2])) {
            return;
        }

        [$selector, $operator, $property] = $matches;
        // The selector ends the access, so the object ends where it starts.
        $object = rtrim(substr($contents, $span->start, $span->end - strlen($selector) - $span->start));
        foreach (self::classes($context, $object, $operator) as $class) {
            foreach (DeprecatedUse::lineage($context->codebase, $class) as $ancestor) {
                $text = $this->symbols->property($ancestor, $property);
                if ($text === null) {
                    continue;
                }

                $this->use->report(
                    $context,
                    self::CODE,
                    $span,
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
     * The classes the property is read from, given the text before the
     * operator and the operator: `->`, `?->` or `::$`.
     *
     * @return list<string>
     */
    private static function classes(NodeAnalysisContext $context, string $object, string $operator): array
    {
        $start = $context->node->span->start;
        if ($operator !== '->' && $operator !== '?->') {
            $class = preg_match(self::CLASS_NAME, $object) === 1
                ? DeprecatedUse::className($context, $object, $start)
                : null;

            return $class === null ? [] : [$class];
        }

        if (strtolower($object) === '$this') {
            $source = $context->source;
            $at = new Span($start, $start + 5);
            $class = FileMembers::of($source)->classAt($context->codebase, $source, $at);

            return $class === null ? [] : [$class->name];
        }

        return Types::names($context->analysis->getExpressionType(new Span($start, $start + strlen($object))));
    }
}
