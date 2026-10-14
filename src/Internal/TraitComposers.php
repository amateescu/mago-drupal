<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use PhpToken;

use function array_key_exists;
use function array_values;
use function file_get_contents;
use function is_file;
use function ltrim;
use function str_contains;
use function strtolower;

/**
 * The classes under a Drupal root that use a trait, directly or through a
 * trait that uses it, read off the PHP files.
 *
 * Mago's class-like targets follow parent classes and interfaces, not
 * traits, so a hook that wants every class with a trait targets the
 * descendants of these classes instead. A trait that uses the trait counts
 * as the trait, and the files are read again for its short name until no
 * new trait turns up. `TraitComposerWalk` reads one file.
 *
 * @internal
 */
final class TraitComposers
{
    /**
     * @param list<non-empty-string> $classes Classes using the trait, as
     *   written.
     * @param list<non-empty-string> $traits Traits using it, as written.
     */
    private function __construct(
        public readonly array $classes,
        public readonly array $traits,
    ) {}

    /**
     * @param list<string> $files
     * @param non-empty-string $trait
     */
    public static function fromFiles(array $files, string $trait): self
    {
        $own = strtolower(ltrim($trait, characters: '\\'));
        /** @var array<string, non-empty-string> $traits */
        $traits = [$own => $trait];
        /** @var array<string, non-empty-string> $classes */
        $classes = [];
        $pending = [$trait];
        while ($pending !== []) {
            $wanted = $pending;
            $pending = [];
            foreach ($files as $file) {
                $source = is_file($file) ? file_get_contents($file) : false;
                if ($source === false || !self::mentionsAny($source, $wanted)) {
                    continue;
                }

                foreach ((new TraitComposerWalk($traits))->walk(PhpToken::tokenize($source)) as [$name, $isTrait]) {
                    $key = strtolower($name);
                    if (!$isTrait) {
                        $classes[$key] = $name;
                        continue;
                    }

                    if (!array_key_exists($key, $traits)) {
                        $traits[$key] = $name;
                        $pending[] = $name;
                    }
                }
            }
        }

        unset($traits[$own]);

        return new self(array_values($classes), array_values($traits));
    }

    /**
     * @param list<string> $names
     */
    private static function mentionsAny(string $source, array $names): bool
    {
        foreach ($names as $name) {
            if (str_contains($source, ClassNames::short($name))) {
                return true;
            }
        }

        return false;
    }
}
