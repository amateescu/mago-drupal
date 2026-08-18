<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function array_key_last;
use function explode;
use function in_array;
use function str_contains;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Reads Drupal's file-naming conventions from a source path.
 *
 * Several rules only apply inside `.module` or `.install` files, and some need
 * the owning extension's machine name. Drupal encodes both in the filename, so
 * this needs no index.
 *
 * @internal
 */
final class DrupalFile
{
    /**
     * File extensions Drupal loads procedurally by convention.
     *
     * tests/corpus/mago.toml and the README recommend scanning the same list;
     * a unit test checks the corpus copy against this constant.
     */
    public const PROCEDURAL_EXTENSIONS = ['module', 'install', 'inc', 'theme', 'profile', 'engine'];

    private function __construct(
        public readonly string $extension,
        public readonly string $name,
    ) {}

    /**
     * Reads the conventions off the path of a file Mago is scanning.
     */
    public static function fromSource(SourceFile $file): self
    {
        // One memo slot keyed by path. Rules call this once per target node
        // and a worker lints one file's nodes consecutively, so this means
        // one parse per file without unbounded growth.
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
     * Reads the conventions off a path.
     */
    public static function fromPath(string $path): self
    {
        $separator = strrpos($path, needle: '/');
        $basename = $separator === false ? $path : substr($path, $separator + 1);

        if (!str_contains($basename, '.')) {
            return new self('', $basename);
        }

        // Drupal names procedural files `<extension-name>.<suffix>`, so the
        // first segment is the machine name even for `foo.pages.inc`.
        $parts = explode('.', $basename);

        return new self(strtolower($parts[array_key_last($parts)]), $parts[0]);
    }

    /**
     * Whether this is a procedural file Drupal loads by convention.
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
     * Whether $function is the named hook implemented by this file's extension.
     */
    public function implementsHook(string $function, string $hook): bool
    {
        return $function === $this->name . '_' . $hook;
    }
}
