<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Traits;

use amateescu\MagoDrupal\Internal\Declaration\ClassNames;
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
 * as the trait, and the files are read again for its short name until a
 * pass doesn't find a new trait. `TraitComposerWalk` reads one file.
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
        return self::scan($files, $trait)[0];
    }

    /**
     * The classes and traits that use the trait, with the files the scan
     * read the declarations of: those that name the trait or one of the
     * traits found.
     *
     * @param list<string> $files
     * @param non-empty-string $trait
     * @return array{self, array<string, true>}
     */
    public static function scan(array $files, string $trait): array
    {
        $walked = [];
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

                $walked[$file] = true;

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

        return [new self(array_values($classes), array_values($traits)), $walked];
    }

    /**
     * Whether the file names the trait or one of the traits that use it, as
     * the scan looks for them.
     *
     * @param non-empty-string $trait
     */
    public function namedIn(string $file, string $trait): bool
    {
        $source = is_file($file) ? file_get_contents($file) : false;

        return $source !== false && self::mentionsAny($source, [$trait, ...$this->traits]);
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
