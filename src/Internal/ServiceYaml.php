<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
use function array_intersect_key;
use function array_map;
use function basename;
use function is_array;
use function is_string;
use function str_starts_with;
use function strstr;

use const ARRAY_FILTER_USE_KEY;

/**
 * Reads the `services:` and `parameters:` sections out of Drupal's
 * `*.services.yml` files.
 *
 * Each definition is either the `'@id'` alias shorthand string or the mapping
 * as written, with the file's `_defaults` and the declaring extension added.
 * Resolution happens in ServiceResolver.
 *
 * @internal
 *
 * @phpstan-type Definition array<array-key, mixed>|string
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ServiceYaml
{
    /**
     * The key under which load() records the extension whose services file
     * declared a service: its machine name, or `core`.
     */
    public const MODULE = '_module';

    /**
     * The key under which load() records that the extension declaring a
     * service is always enabled: core, or a module whose info file says
     * `required: true`.
     */
    public const ALWAYS_ON = '_always_on';

    /**
     * Keys that decide a definition's visibility without the file's
     * defaults: its own `public:`, or the parent it copies.
     */
    private const OWN_VISIBILITY = ['public' => true, 'parent' => true];

    /**
     * Keys that decide whether a definition autowires without the file's
     * defaults: its own `autowire:`, or the parent it copies.
     */
    private const OWN_AUTOWIRING = ['autowire' => true, 'parent' => true];

    private function __construct() {}

    /**
     * Merges the definitions of every file, later files overriding earlier ids.
     *
     * @param list<string> $paths
     * @return array<non-empty-string, Definition>
     */
    public static function load(array $paths): array
    {
        return self::read($paths)[0];
    }

    /**
     * Reads the services and the parameter kinds of every file in one parse.
     *
     * Later files override earlier service ids. A parameter keeps the kinds
     * of every file that defines it, see ServiceParameters.
     *
     * @param list<string> $paths
     * @return array{array<non-empty-string, Definition>, array<non-empty-string, array<string, true>>}
     */
    public static function read(array $paths): array
    {
        $definitions = [];
        $parameters = [];
        foreach ($paths as $path) {
            try {
                /** @var mixed $document */
                $document = Yaml::parseFile($path, Yaml::PARSE_CUSTOM_TAGS);
            } catch (ParseException) {
                // A broken YAML file is Drupal's problem to report; the index
                // just goes without that extension's services.
                continue;
            }

            foreach (ServiceParameters::kindsIn($document) as $name => $kind) {
                $parameters[$name][$kind] = true;
            }

            // `node.services.yml` belongs to `node`, `core.services.yml` to core.
            $module = strstr(basename($path), needle: '.services.yml', before_needle: true);
            $alwaysOn = $module === 'core' || ServiceModuleInfo::required($path);
            foreach (self::definitions($document) as $id => $definition) {
                $definitions[$id] = is_array($definition) && $module !== false
                    ? [...$definition, self::MODULE => $module, self::ALWAYS_ON => $alwaysOn]
                    : $definition;
            }
        }

        return [$definitions, $parameters];
    }

    /**
     * Extracts the `services:` section of one parsed document.
     *
     * @return array<non-empty-string, Definition>
     */
    public static function definitions(mixed $document): array
    {
        $named = array_filter(
            Shape::arrayAt($document, 'services'),
            // `_defaults`, `_instanceof` and any other `_` key are compiler
            // directives, not services.
            static fn(mixed $id): bool => is_string($id) && $id !== '' && !str_starts_with($id, '_'),
            ARRAY_FILTER_USE_KEY,
        );

        // `_defaults: {public: false}` makes every service of the file private
        // unless it says otherwise, and `autowire: true` autowires them. The
        // defaults do not outlive the file, so each definition keeps its own
        // copy.
        $defaults = Shape::arrayAt($document, 'services', '_defaults');
        /** @var array<non-empty-string, Definition> $definitions */
        $definitions = array_map(
            ($defaults['public'] ?? null) === false ? self::privately(...) : self::asWritten(...),
            $named,
        );

        return ($defaults['autowire'] ?? null) === true ? array_map(self::autowired(...), $definitions) : $definitions;
    }

    /**
     * One definition of a file whose defaults autowire it, unless it says
     * otherwise itself. A `parent:` child is left alone: Drupal's loader
     * resets a child's changes after the defaults, so the child autowires
     * only when its parent does.
     *
     * @param Definition $definition
     * @return Definition
     */
    private static function autowired(array|string $definition): array|string
    {
        return (
            is_array($definition) && array_intersect_key($definition, self::OWN_AUTOWIRING) === []
                ? [...$definition, 'autowire' => true]
                : $definition
        );
    }

    /**
     * One definition as written: the `'@id'` shorthand string, or the mapping.
     *
     * @return Definition
     */
    private static function asWritten(mixed $definition): array|string
    {
        return is_string($definition) ? $definition : Shape::array($definition) ?? [];
    }

    /**
     * One definition of a file whose defaults make it private, unless it says
     * otherwise itself. The `'@id'` shorthand takes the long alias form, the
     * only one that can carry `public`. A `parent:` child keeps its parent's
     * visibility, since Symfony resolves a child from its parent and only its
     * own `public:` wins over that.
     *
     * @return Definition
     */
    private static function privately(mixed $definition): array|string
    {
        $definition = self::asWritten($definition);
        if (is_string($definition)) {
            return str_starts_with($definition, '@') ? ['alias' => $definition, 'public' => false] : $definition;
        }

        return (
            array_intersect_key($definition, self::OWN_VISIBILITY) !== []
                ? $definition
                : [...$definition, 'public' => false]
        );
    }
}
