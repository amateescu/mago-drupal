<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\ServiceDefinition;
use Mago\Sdk\Analyzer\Codebase;

/**
 * What the container providers target and what a service id resolves to.
 *
 * @internal
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
     * The class behind a service id, or null when none of the candidates is
     * in the codebase.
     *
     * A decorator declared by a module this run does not analyze is not in
     * the codebase, and the class it took over is then the best answer left.
     * Without it a single contrib decorator of a core service turns every
     * core caller of that id into a bare `object`.
     *
     * @param non-empty-string|null $fallback Used when the service names no
     *   class the codebase has, such as an id that is itself a class name.
     * @return non-empty-string|null
     */
    public static function classFor(Codebase $codebase, ?ServiceDefinition $service, ?string $fallback = null): ?string
    {
        foreach ([$service?->class, $service?->undecoratedClass, $fallback] as $class) {
            if ($class !== null && $codebase->classLikeExists($class)) {
                return $class;
            }
        }

        return null;
    }
}
