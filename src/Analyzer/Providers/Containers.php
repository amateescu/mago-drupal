<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\ServiceDefinition;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;

use function array_diff;
use function array_intersect;
use function array_slice;
use function array_values;
use function in_array;
use function strtolower;

/**
 * What the container providers target and what a service id resolves to.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class Containers
{
    /**
     * Drupal's container interfaces extend this one, so targeting it covers
     * every Drupal container without matching unrelated PSR-11 containers.
     */
    public const INTERFACE = 'Symfony\Component\DependencyInjection\ContainerInterface';

    public const CLASS_RESOLVER = 'Drupal\Core\DependencyInjection\ClassResolverInterface';

    /**
     * `ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE`, the default and
     * the only behavior under which a missing service throws. Every other
     * behavior makes `get()` return null for it.
     */
    public const EXCEPTION_ON_INVALID_REFERENCE = 1;

    private function __construct() {}

    /**
     * The type behind a service id, or null when none of the candidates is
     * in the codebase.
     *
     * A decorator only replaces the service while the module that declares
     * it is enabled, and a site need not enable an optional module such as
     * Workspaces. So an id decorated from another module gets the type both
     * classes share: the decorated class when the decorator extends it,
     * otherwise the most specific classes and interfaces they both have, as
     * an intersection when there are several. With nothing in common the
     * declared type stays. A decorator from the service's own module, from
     * core or from a required module is always there and keeps its class. A
     * decorator class that is not in the codebase, such as one from a module
     * this run does not analyze, leaves the class it took over.
     *
     * @param non-empty-string|null $fallback Used when the service names no
     *   class the codebase has, such as an id that is itself a class name.
     */
    public static function typeFor(Codebase $codebase, ?ServiceDefinition $service, ?string $fallback = null): ?Type
    {
        $class = $service?->class;
        $original = $service?->undecoratedClass;
        if (
            $service?->optionalDecorator === true
            && $class !== null
            && $original !== null
            && $codebase->checkMultipleClassLikesExist([$class, $original]) === [true, true]
        ) {
            return self::intersection(self::shared($codebase, $class, $original));
        }

        foreach ([$class, $original, $fallback] as $candidate) {
            if ($candidate !== null && $codebase->classLikeExists($candidate)) {
                return Type::namedObject($candidate);
            }
        }

        return null;
    }

    /**
     * The most specific types a decorator and the class it decorates both
     * have: the decorated class when the decorator extends it, otherwise
     * every common class or interface no other common one extends.
     *
     * @param non-empty-string $decorator
     * @param non-empty-string $original
     * @return list<non-empty-string>
     */
    private static function shared(Codebase $codebase, string $decorator, string $original): array
    {
        $decoratorAncestors = $codebase->getClassAncestors($decorator);
        if (in_array(strtolower($original), $decoratorAncestors, strict: true)) {
            return [$original];
        }

        $common = array_values(array_intersect($codebase->getClassAncestors($original), $decoratorAncestors));
        $dominated = [];
        foreach ($common === [] ? [] : $codebase->getMultipleClassAncestors($common) as $ancestors) {
            $dominated = [...$dominated, ...$ancestors];
        }

        $names = [];
        foreach (array_diff($common, $dominated) as $lowercased) {
            $name = $codebase->getClassLike($lowercased)?->originalName;
            if ($name !== null && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * One object type holding every class, or null for none.
     *
     * @param list<non-empty-string> $classes
     */
    private static function intersection(array $classes): ?Type
    {
        if ($classes === []) {
            return null;
        }

        $others = [];
        foreach (array_slice($classes, offset: 1) as $class) {
            $others[] = new NamedObjectType($class, null, null, false, false, null, false);
        }

        return Type::fromAtomic(
            new NamedObjectType($classes[0], null, null, false, false, $others === [] ? null : $others, false),
        );
    }
}
