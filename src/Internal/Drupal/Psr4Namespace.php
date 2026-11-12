<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Drupal;

use function preg_match;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Works out the namespace that Drupal's autoloader expects for a file.
 *
 * Drupal registers `Drupal\{name}` for the `src/` directory of a module,
 * theme or profile, and `Drupal\Tests\{name}` for its `tests/src/`
 * directory. The extension is the one of the nearest `*.info.yml` file.
 * Core's `lib/Drupal/Core`, `lib/Drupal/Component` and `tests/Drupal/*Tests`
 * directories are named after their path from `Drupal`.
 *
 * @internal
 */
final class Psr4Namespace
{
    private const ROOTS = ['/src/' => 'Drupal\\', '/tests/src/' => 'Drupal\\Tests\\'];

    private const CORE = '#/core/(?:lib/(Drupal/(?:Core|Component)(?:/.*)?)|tests/(Drupal/[A-Za-z]*Tests(?:/.*)?))$#';

    private function __construct() {}

    /**
     * The namespace for the file at $path, or null for a file outside the
     * directories with a known namespace.
     */
    public static function of(string $path): ?string
    {
        $directory = NearestFile::directoryOf($path);
        if ($directory === null) {
            return null;
        }

        $extension = InfoFile::extension($path);
        if ($extension === null) {
            // A `src` directory below core's own belongs to a fixture
            // package with a namespace of its own.
            $match = [];

            return preg_match(self::CORE, $directory, $match) === 1 && !str_contains($directory . '/', '/src/')
                ? str_replace('/', replace: '\\', subject: $match[2] ?? $match[1])
                : null;
        }

        [$root, $name] = $extension;
        $inside = substr($directory, strlen($root)) . '/';
        foreach (self::ROOTS as $prefix => $namespace) {
            if (!str_starts_with($inside, $prefix)) {
                continue;
            }

            $below = substr($inside, strlen($prefix) - 1, length: -1);

            return $namespace . $name . str_replace('/', replace: '\\', subject: $below);
        }

        return null;
    }
}
