<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function file_get_contents;
use function is_array;
use function is_string;
use function ltrim;
use function str_contains;

/**
 * Reads the classes that the nearest `*.services.yml` file registers as
 * services, the file that Coder's DrupalPractice standard reads.
 *
 * @internal
 */
final class ServiceClasses
{
    private function __construct() {}

    /**
     * Whether $class, a fully qualified name without a leading backslash,
     * is a service of the code that holds the file at $path.
     */
    public static function has(string $path, string $class): bool
    {
        $directory = NearestFile::directoryOf($path);
        $file = $directory === null ? null : NearestFile::find($directory, ['*.services.yml']);

        return $file !== null && (self::classes($file)[$class] ?? false);
    }

    /**
     * The classes in a services file: each `class` value, and each service
     * whose name is a class, as in `Drupal\foo\Bar: ~`. A file that does
     * not parse has none.
     *
     * @return array<string, true>
     */
    private static function classes(string $file): array
    {
        /** @var array<string, array<string, true>> $memo */
        static $memo = [];

        $classes = $memo[$file] ?? null;
        if ($classes !== null) {
            return $classes;
        }

        $parsed = YamlFile::decode((string) file_get_contents($file)) ?? [];
        /** @var mixed $services */
        $services = $parsed['services'] ?? [];
        $classes = [];
        /** @var mixed $service */
        foreach (is_array($services) ? $services : [] as $name => $service) {
            if (is_array($service) && is_string($service['class'] ?? null)) {
                $classes[ltrim($service['class'], characters: '\\')] = true;
            }

            if (is_string($name) && str_contains($name, needle: '\\')) {
                $classes[ltrim($name, characters: '\\')] = true;
            }
        }

        $memo[$file] = $classes;

        return $classes;
    }
}
