<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;
use function array_keys;
use function explode;
use function ltrim;
use function strrpos;
use function strtolower;
use function substr;

/**
 * Class-likes, class constants, properties and methods whose docblock marks
 * them `@deprecated`, with their `@deprecated` text.
 *
 * Mago reports a deprecated class only where it is instantiated, extended or
 * used as a trait, and never a deprecated class constant or property. The
 * hooks that report the rest need the names up front: an interface to target
 * its implementers, and the short names to send most nodes back after a
 * string compare. `DeprecatedSymbolScan` reads them off the files.
 *
 * @internal
 */
final class DeprecatedSymbols
{
    /**
     * Lowercased short names of the class-likes.
     *
     * @var array<string, true>
     */
    private readonly array $shortNames;

    /**
     * @var array<string, true>
     */
    private readonly array $constantNames;

    /**
     * Without the `$`.
     *
     * @var array<string, true>
     */
    private readonly array $propertyNames;

    /**
     * `class::method`, both lowercased, to the text.
     *
     * @var array<string, string>
     */
    private readonly array $lowercasedMethods;

    /**
     * @param array<string, string> $classLikes Lowercased name to the
     *   `@deprecated` text.
     * @param list<non-empty-string> $interfaces Deprecated interfaces, as
     *   written.
     * @param array<string, string> $constants `class::NAME`, the class
     *   lowercased, to the text.
     * @param array<string, string> $properties `class::$name`, the class
     *   lowercased, to the text.
     * @param array<string, string> $methods `Class::method`, both as written,
     *   to the text.
     */
    public function __construct(
        private readonly array $classLikes,
        private readonly array $interfaces,
        private readonly array $constants,
        private readonly array $properties,
        private readonly array $methods,
    ) {
        $shortNames = [];
        foreach (array_keys($classLikes) as $class) {
            $shortNames[substr($class, (int) strrpos('\\' . $class, needle: '\\'))] = true;
        }

        $constantNames = [];
        foreach (array_keys($constants) as $constant) {
            $constantNames[substr($constant, (int) strrpos($constant, needle: ':') + 1)] = true;
        }

        $propertyNames = [];
        foreach (array_keys($properties) as $property) {
            $propertyNames[substr($property, (int) strrpos($property, needle: '$') + 1)] = true;
        }

        $lowercasedMethods = [];
        foreach ($methods as $method => $text) {
            $lowercasedMethods[strtolower($method)] = $text;
        }

        $this->lowercasedMethods = $lowercasedMethods;
        $this->shortNames = $shortNames;
        $this->constantNames = $constantNames;
        $this->propertyNames = $propertyNames;
    }

    public function isEmpty(): bool
    {
        return $this->classLikes === [] && $this->constants === [] && $this->properties === [] && $this->methods === [];
    }

    /**
     * The `@deprecated` text of a class-like, or null when it is not
     * deprecated.
     */
    public function classLike(string $name): ?string
    {
        return $this->classLikes[strtolower(ltrim($name, characters: '\\'))] ?? null;
    }

    /**
     * Whether a deprecated class-like has this short name, any case.
     */
    public function hasShortName(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->shortNames);
    }

    /**
     * The deprecated interfaces, as written.
     *
     * @return list<non-empty-string>
     */
    public function interfaces(): array
    {
        return $this->interfaces;
    }

    /**
     * The `@deprecated` text of a constant declared on the class, or null.
     */
    public function constant(string $class, string $name): ?string
    {
        return $this->constants[strtolower($class) . '::' . $name] ?? null;
    }

    /**
     * Whether some class declares a deprecated constant with this name.
     */
    public function hasConstantName(string $name): bool
    {
        return array_key_exists($name, $this->constantNames);
    }

    /**
     * The `@deprecated` text of a property declared on the class, or null.
     */
    public function property(string $class, string $name): ?string
    {
        return $this->properties[strtolower($class) . '::$' . $name] ?? null;
    }

    /**
     * Whether some class declares a deprecated property with this name.
     */
    public function hasPropertyName(string $name): bool
    {
        return array_key_exists($name, $this->propertyNames);
    }

    /**
     * The deprecated methods as class and name, written as declared.
     *
     * @return list<array{non-empty-string, non-empty-string}>
     */
    public function methods(): array
    {
        $methods = [];
        foreach (array_keys($this->methods) as $method) {
            [$class, $name] = explode(separator: '::', string: $method, limit: 2) + ['', ''];
            if ($class !== '' && $name !== '') {
                $methods[] = [$class, $name];
            }
        }

        return $methods;
    }

    /**
     * The `@deprecated` text of a method declared on the class, or null.
     */
    public function method(string $class, string $name): ?string
    {
        return $this->lowercasedMethods[strtolower($class . '::' . $name)] ?? null;
    }
}
