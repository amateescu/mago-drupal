<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\ServiceWiring;
use amateescu\MagoDrupal\Internal\TestFiles;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Metadata\MethodFields;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;
use Mago\Sdk\Analyzer\Type\Visibility;

use function count;
use function file_get_contents;
use function is_file;
use function str_contains;
use function strtolower;
use function substr;

/**
 * Reports a service whose `arguments:` do not fit its class's constructor.
 *
 * The count follows the container: a `parent:` child's arguments merged into
 * its parent's, and one more for each tag whose compiler pass adds an
 * argument. Only a service whose arguments come from the services files
 * alone is counted, see ServiceWiring. Too few is an ArgumentCountError when
 * the container builds the service. Too many is a warning: PHP drops the
 * rest, and so does the container when the class has no constructor.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ServiceArgumentsCheck implements MetadataCheck
{
    public const CODE = 'service-argument-count';

    private const FIELDS =
        MethodFields::NAMES | MethodFields::LOCATIONS | MethodFields::PARAMETERS | MethodFields::METHOD_DETAILS;

    /**
     * @param Closure(Codebase): ServiceWiring $wiring Returns the argument
     *   counts of the services of each class.
     * @param Closure(Codebase): array<non-empty-string, true> $altered
     *   Returns the ids a service provider or compiler pass may change.
     */
    public function __construct(
        private readonly Closure $wiring,
        private readonly Closure $altered,
    ) {}

    public function textGate(): ?string
    {
        return null;
    }

    /**
     * Whether any of the names is the class of a service with a known
     * argument count. The class hook asks this for every class, so it is a
     * lookup per name.
     *
     * @param list<non-empty-string> $names
     */
    public function namedBy(Codebase $codebase, array $names): bool
    {
        $wiring = ($this->wiring)($codebase);
        foreach ($names as $name) {
            if ($wiring->hasArguments($name)) {
                return true;
            }
        }

        return false;
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $metadata = $class->class;
        // A class whose parent or trait Mago has not scanned may have a
        // constructor the metadata does not show.
        if (
            $metadata->flags->contains(MetadataFlags::ABSTRACT)
            || $metadata->hasIncompleteHierarchy()
            || TestFiles::isTest($metadata->location->file ?? '')
        ) {
            return;
        }

        $services = ($this->wiring)($class->codebase)->arguments($metadata->name);
        // One request gives the constructor the class has, its own or an
        // inherited one, and the class that declares it.
        $constructor = $services === []
            ? null
            : $class->codebase->findMethods(class: $metadata->name, name: '__construct', fields: self::FIELDS)[0]
            ?? null;
        if ($services === [] || $constructor !== null && $constructor->visibility !== Visibility::Public) {
            return;
        }

        [$required, $maximum] = self::range($constructor);
        $own =
            $constructor !== null && strtolower($constructor->identifier->class ?? '') === strtolower($metadata->name);
        $location = $own
            ? $constructor->nameLocation ?? $constructor->location ?? $metadata->location
            : $metadata->nameLocation ?? $metadata->location;
        $altered = null;
        foreach ($services as $service) {
            if ($service->count >= $required && ($maximum === null || $service->count <= $maximum)) {
                continue;
            }

            // A provider or compiler pass that names the id may add or
            // replace arguments after the services files are loaded.
            $altered ??= ($this->altered)($class->codebase);
            if ($altered[$service->id] ?? false) {
                continue;
            }

            $passes =
                "The service \"{$service->id}\" in {$service->file} passes {$service->count} "
                . ($service->count === 1 ? 'argument' : 'arguments');
            if ($service->count < $required) {
                $reporter->error(self::CODE, Reporter::issue(
                    "{$passes} to {$class->name()}::__construct(), which requires {$required}.",
                    $location,
                    'The container throws an ArgumentCountError when it builds the service. Add the missing arguments to the service definition.',
                ));
                continue;
            }

            // Core reads the arguments a deprecation adds through
            // func_get_args() before it adds the parameter.
            if ($constructor !== null && self::readsAnyArguments($constructor)) {
                continue;
            }

            $reporter->warning(self::CODE, Reporter::issue(
                $constructor === null
                    ? "{$passes}, but {$class->name()} has no constructor."
                    : "{$passes} to {$class->name()}::__construct(), which takes {$maximum}.",
                $location,
                'PHP drops the extra arguments silently. Remove them from the service definition.',
            ));
        }
    }

    /**
     * Whether the constructor reads its arguments with `func_get_args()` or
     * `func_get_arg()`, read off disk. A constructor that cannot be read
     * counts as reading them, so its arguments are not counted.
     */
    private static function readsAnyArguments(MethodMetadataProjection $constructor): bool
    {
        $location = $constructor->location;
        $file = $location?->file;
        $contents = $file !== null && is_file($file) ? file_get_contents($file) : false;

        return $location === null
        || $contents === false
        || str_contains(substr($contents, $location->span->start, $location->span->length()), 'func_get_arg');
    }

    /**
     * The number of arguments the constructor requires and the most it
     * takes, null for no limit. PHP requires every parameter up to the last
     * one without a default, even where an earlier one has a default.
     *
     * @return array{int, int|null}
     */
    private static function range(?MethodMetadataProjection $constructor): array
    {
        if ($constructor === null) {
            return [0, 0];
        }

        $parameters = $constructor->parameters ?? [];
        $required = 0;
        foreach ($parameters as $position => $parameter) {
            if (($parameter->flags->bits & (MetadataFlags::HAS_DEFAULT | MetadataFlags::VARIADIC)) !== 0) {
                continue;
            }

            $required = $position + 1;
        }

        $last = $parameters[count($parameters) - 1] ?? null;

        return [$required, $last?->flags->contains(MetadataFlags::VARIADIC) === true ? null : count($parameters)];
    }
}
