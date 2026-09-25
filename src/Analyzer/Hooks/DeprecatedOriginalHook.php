<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Providers\MagicProperties;
use amateescu\MagoDrupal\Internal\DeprecationScopes;
use amateescu\MagoDrupal\Internal\InheritedDeprecation;
use amateescu\MagoDrupal\Internal\NamedFunctions;
use amateescu\MagoDrupal\Internal\TestFiles;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use WeakMap;

use function rtrim;
use function str_ends_with;
use function strlen;
use function substr;
use function substr_compare;

/**
 * Reports the magic `original` property Drupal 11.2 deprecates on entities.
 *
 * `EntityBase::__get()`, `__set()`, `__isset()` and `__unset()` all trigger
 * the deprecation. Mago's SDK targets node kinds, not property names, so
 * every property access in every file comes through here. A byte compare on
 * the end of the access sends all but `->original` straight back; for those,
 * the receiver's type is fetched from the host, and only an entity that does
 * not declare `$original` itself is reported. The deprecation scopes Mago's
 * own deprecation codes get apply here too, `backwardsCompatibleCall()` and
 * overrides of deprecated methods included. The text fast path and the repeats Mago does not analyze again
 * are where the branch count comes from.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class DeprecatedOriginalHook implements NodeAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/deprecated-original`.
     */
    public const CODE = 'deprecated-original';

    private const ENTITY = 'Drupal\Core\Entity\EntityInterface';

    private const PROPERTY = 'original';

    /**
     * Per file, whether each receiver, keyed by its source text, was an
     * entity at its last access Mago analyzed. Keyed by the file's source
     * object, since a host query suspends the request and another file's
     * nodes can run in between; an entry goes away with its file.
     *
     * @var WeakMap<SourceFile, array<string, bool>>
     */
    private WeakMap $entities;

    public function __construct()
    {
        $this->entities = new WeakMap();
    }

    public function getTargets(): array
    {
        return [NodeKind::PropertyAccess, NodeKind::NullSafePropertyAccess];
    }

    public function getRequirements(): array
    {
        // The receiver span is cut from the file text. Mago ships a file's
        // text once for all hooks, and other hooks already ask for it.
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $span = $context->node->span;
        $contents = $context->source->contents;
        $length = strlen(self::PROPERTY);
        if (
            $span->length() <= $length
            || substr_compare($contents, self::PROPERTY, $span->end - $length, $length) !== 0
        ) {
            return;
        }

        $receiver = self::receiver($contents, $span, $length);
        if ($receiver === null || TestFiles::isTestOrHookDocumentation($context->analysis->file)) {
            return;
        }

        // Mago hands back the narrowed type of an access it has already
        // seen in the same scope without analyzing the receiver again, so a
        // repeat has no receiver type and reuses the earlier answer.
        $source = $context->source;
        $object = $source->getText($receiver);
        $type = $context->analysis->getExpressionType($receiver);
        $answers = $this->entities[$source] ?? [];
        $entity = $type === null ? $answers[$object] ?? false : ($answers[$object] = self::onEntity($context, $type));
        $this->entities[$source] = $answers;
        if (
            !$entity
            || DeprecationScopes::marked($contents) && DeprecationScopes::of($contents)->covers($span)
            || InheritedDeprecation::covers($context->codebase, $source->path, NamedFunctions::of($contents), $span)
        ) {
            return;
        }

        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                'The original property is deprecated in drupal:11.2.0 and is removed from drupal:12.0.0.',
                $span,
                'deprecated property',
            )->withHelp('Call getOriginal() to read it and setOriginal() to set it.')->withLink(
                'https://www.drupal.org/node/3295826',
            ),
        );
    }

    /**
     * The span of the object in `<object>->original` or `<object>?->original`,
     * or null when the selector is not `original` after an arrow.
     */
    private static function receiver(string $contents, Span $span, int $length): ?Span
    {
        $object = rtrim(substr($contents, $span->start, $span->length() - $length));
        if (!str_ends_with($object, '->')) {
            return null;
        }

        $object = substr($object, offset: 0, length: -2);
        $object = rtrim(str_ends_with($object, '?') ? substr($object, offset: 0, length: -1) : $object);

        return $object === '' ? null : new Span($span->start, $span->start + strlen($object));
    }

    /**
     * Whether some class in the receiver type is an entity that leaves
     * `$original` to the magic methods.
     */
    private static function onEntity(NodeAnalysisContext $context, ?Type $receiver): bool
    {
        foreach (Types::names($receiver) as $class) {
            if (
                !MagicProperties::declaredOn($context->codebase, $class, self::PROPERTY)
                && $context->types->isContainedBy(Type::namedObject($class), Type::namedObject(self::ENTITY))
            ) {
                return true;
            }
        }

        return false;
    }
}
