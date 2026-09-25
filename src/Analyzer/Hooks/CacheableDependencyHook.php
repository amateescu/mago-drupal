<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Arguments;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function array_map;
use function in_array;
use function strtolower;

/**
 * Reports `addCacheableDependency()` handed something that carries no
 * cacheability.
 *
 * Ports phpstan-drupal's CacheableDependencyRule. A dependency that does not
 * implement `CacheableDependencyInterface` is treated as uncacheable, so the
 * whole thing it was added to drops to max-age 0. Only a type that definitely
 * cannot implement the interface is reported: a scalar, an array, null, or a
 * final class that does not implement it. `mixed`, a plain `object`, an
 * interface and a class Mago has not scanned all stay quiet, since an object
 * could implement the interface alongside them, and so does a class that can
 * be extended, since the subclass passed at runtime may implement it.
 *
 * The receivers fall in two groups by where the dependency sits, so the
 * plugin registers the hook once per group.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class CacheableDependencyHook implements MethodCallAnalysisHook
{
    public const CODE = 'cacheable-dependency';

    /**
     * Receivers taking the dependency as their first argument.
     *
     * @var non-empty-list<string>
     */
    public const REFINABLE = [
        'Drupal\Core\Cache\RefinableCacheableDependencyInterface',
        'Drupal\Core\Cache\CacheableResponseInterface',
        'Drupal\Core\Plugin\Context\ContextInterface',
    ];

    /**
     * The renderer takes the render array first and the dependency second.
     *
     * @var non-empty-list<string>
     */
    public const RENDERER = ['Drupal\Core\Render\RendererInterface'];

    private const DEPENDENCY = 'Drupal\Core\Cache\CacheableDependencyInterface';

    private const METHOD = 'addCacheableDependency';

    /**
     * @param non-empty-list<string> $receivers REFINABLE or RENDERER.
     */
    public function __construct(
        private readonly array $receivers,
    ) {}

    public function getTargets(): array
    {
        return array_map(static fn(string $class): MethodTarget => MethodTarget::exact(
            $class,
            self::METHOD,
        ), $this->receivers);
    }

    public function getRequirements(): array
    {
        // The refinable receivers take the dependency alone, so it is the first
        // argument type however the call names it; they do not agree on the
        // parameter's name either. The renderer takes the render array first,
        // and a call naming its arguments can swap the two.
        return (
            $this->receivers === self::RENDERER
                ? [
                    FileAnalysisRequirement::ArgumentTypes,
                    FileAnalysisRequirement::TargetSubtree,
                    FileAnalysisRequirement::SourceText,
                ]
                : [FileAnalysisRequirement::ArgumentTypes]
        );
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $dependency = $this->receivers === self::RENDERER
            ? Arguments::type($context, position: 1, names: 'dependency')
            : $context->argumentTypes[0] ?? null;
        if ($dependency === null) {
            return;
        }

        if (!self::uncacheable($context->codebase, $dependency)) {
            return;
        }

        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                "A dependency of type `{$dependency}` carries no cacheability, so adding it turns caching off.",
                $context->node->span,
                'not a CacheableDependencyInterface',
            )->withHelp(
                'Pass an object implementing CacheableDependencyInterface, or add the cache tags, contexts and max-age by hand.',
            ),
        );
    }

    /**
     * Whether every member of the type is definitely not a cacheable
     * dependency.
     */
    private static function uncacheable(Codebase $codebase, Type $type): bool
    {
        foreach ($type->atomicTypes as $atomic) {
            if ($atomic instanceof NamedObjectType) {
                if (!self::classCannotBeCacheable($codebase, $atomic)) {
                    return false;
                }

                continue;
            }

            // A scalar, an array or null carries no cacheability; anything
            // else, `mixed` and a bare `object` included, might.
            if (!Types::isScalarOnly(Type::fromAtomic($atomic))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether a named object, intersection members included, is a final class
     * that does not implement the interface.
     */
    private static function classCannotBeCacheable(Codebase $codebase, NamedObjectType $atomic): bool
    {
        foreach ([$atomic, ...($atomic->intersections ?? [])] as $member) {
            if (!$member instanceof NamedObjectType) {
                return false;
            }

            $metadata = $codebase->getClassLike($member->name);
            // An unscanned name, or an interface a class could implement
            // alongside the dependency interface, is not a decision.
            if ($metadata === null || $metadata->kind !== ClassLikeKind::Class_) {
                return false;
            }

            // The declared type is an upper bound, so a class that can be
            // extended says nothing: core's own CacheableHttpException
            // extends Symfony's HttpException to add the cacheability.
            if (!$metadata->flags->contains(MetadataFlags::FINAL)) {
                return false;
            }

            if (in_array(strtolower(self::DEPENDENCY), $metadata->parentInterfaces, strict: true)) {
                return false;
            }
        }

        return true;
    }
}
