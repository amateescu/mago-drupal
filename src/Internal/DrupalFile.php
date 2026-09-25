<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function array_key_last;
use function explode;
use function in_array;
use function str_contains;
use function str_ends_with;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Reads Drupal's file-naming conventions from a source path.
 *
 * Several rules apply only inside `.module` or `.install` files. Some of
 * them must have the machine name of the extension that owns the file.
 * Drupal encodes both facts in the file name, so this class needs no index.
 *
 * @internal
 */
final class DrupalFile
{
    /**
     * The file extensions that Drupal loads procedurally by convention.
     *
     * tests/corpus/mago.toml and the README recommend the same list for the
     * scan. A unit test compares the corpus copy with this constant.
     */
    public const PROCEDURAL_EXTENSIONS = ['module', 'install', 'inc', 'theme', 'profile', 'engine'];

    private function __construct(
        public readonly string $extension,
        public readonly string $name,
        public readonly string $basename = '',
    ) {}

    /**
     * Reads the conventions from the path of a file that Mago scans.
     */
    public static function fromSource(SourceFile $file): self
    {
        // One memo slot, keyed by path. The rules call this once per target
        // node, and a worker lints the nodes of one file in sequence, so
        // there is one parse per file, and the memo does not grow.
        /** @var array<string, self> $memo */
        static $memo = [];

        $parsed = $memo[$file->path] ?? null;
        if ($parsed === null) {
            $parsed = self::fromPath($file->path);
            $memo = [$file->path => $parsed];
        }

        return $parsed;
    }

    /**
     * Reads the conventions from a path.
     */
    public static function fromPath(string $path): self
    {
        $separator = strrpos($path, needle: '/');
        $basename = $separator === false ? $path : substr($path, $separator + 1);

        if (!str_contains($basename, '.')) {
            return new self('', $basename, $basename);
        }

        // Drupal names a procedural file `<extension-name>.<suffix>`, so the
        // first segment is the machine name, also for `foo.pages.inc`.
        $parts = explode('.', $basename);

        return new self(strtolower($parts[array_key_last($parts)]), $parts[0], $basename);
    }

    /**
     * Whether this is a procedural file that Drupal loads by convention.
     */
    public function isProcedural(): bool
    {
        return in_array($this->extension, self::PROCEDURAL_EXTENSIONS, strict: true);
    }

    /**
     * Whether this is a `.module` file.
     */
    public function isModule(): bool
    {
        return $this->extension === 'module';
    }

    /**
     * Whether this is an `.install` file.
     */
    public function isInstall(): bool
    {
        return $this->extension === 'install';
    }

    /**
     * Whether this file holds update code: the `hook_update_N()` functions of
     * an `.install` file, or a `.post_update.php`.
     */
    public function isUpdate(): bool
    {
        return $this->isInstall() || str_ends_with(strtolower($this->basename), '.post_update.php');
    }

    /**
     * Whether $function is the named hook of the extension that owns this file.
     */
    public function implementsHook(string $function, string $hook): bool
    {
        return $function === $this->name . '_' . $hook;
    }
}
