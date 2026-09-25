<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

use function array_filter;
use function array_key_exists;
use function array_map;
use function is_string;
use function str_starts_with;

use const ARRAY_FILTER_USE_KEY;

/**
 * Reads the `services:` sections out of Drupal's `*.services.yml` files.
 *
 * Each definition is either the `'@id'` alias shorthand string or the mapping
 * as written, untouched. Resolution happens in ServiceResolver.
 *
 * @internal
 *
 * @phpstan-type Definition array<array-key, mixed>|string
 */
final class ServiceYaml
{
    private function __construct() {}

    /**
     * Merges the definitions of every file, later files overriding earlier ids.
     *
     * @param list<string> $paths
     * @return array<non-empty-string, Definition>
     */
    public static function load(array $paths): array
    {
        $definitions = [];
        foreach ($paths as $path) {
            try {
                $definitions = [...$definitions, ...self::definitions(Yaml::parseFile($path, Yaml::PARSE_CUSTOM_TAGS))];
            } catch (ParseException) {
                // A broken YAML file is Drupal's problem to report; the index
                // just goes without that extension's services.
                continue;
            }
        }

        return $definitions;
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
        // unless it says otherwise. The defaults do not outlive the file, so
        // each definition keeps its own copy.
        $private = (Shape::arrayAt($document, 'services', '_defaults')['public'] ?? null) === false;

        /** @var array<non-empty-string, Definition> */
        return array_map($private ? self::privately(...) : self::asWritten(...), $named);
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
     * otherwise itself. The `'@id'` shorthand is an alias, and Drupal keeps
     * every alias gettable, so it stays as written.
     *
     * @return Definition
     */
    private static function privately(mixed $definition): array|string
    {
        $definition = self::asWritten($definition);

        return (
            is_string($definition) || array_key_exists('public', $definition)
                ? $definition
                : [...$definition, 'public' => false]
        );
    }
}
