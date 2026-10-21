<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Deprecation;

use function array_keys;
use function explode;
use function ltrim;
use function strtolower;

/**
 * Class-likes and interface methods whose docblock marks them `@deprecated`,
 * with their `@deprecated` text.
 *
 * Mago reports a deprecated class-like only where it is instantiated,
 * extended, used as a trait or extended by an interface. The hooks that
 * report the rest need the names up front: an interface or an interface
 * method to target, and the class-likes to send most nodes back after a
 * lookup. `DeprecatedSymbolScan` reads them off the files.
 *
 * @internal
 */
final class DeprecatedSymbols
{
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
     * @param array<string, string> $methods `Class::method`, both as written,
     *   to the text.
     */
    public function __construct(
        private readonly array $classLikes,
        private readonly array $interfaces,
        private readonly array $methods,
    ) {
        $lowercasedMethods = [];
        foreach ($methods as $method => $text) {
            $lowercasedMethods[strtolower($method)] = $text;
        }

        $this->lowercasedMethods = $lowercasedMethods;
    }

    public function isEmpty(): bool
    {
        return $this->classLikes === [] && $this->methods === [];
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
     * The deprecated interfaces, as written.
     *
     * @return list<non-empty-string>
     */
    public function interfaces(): array
    {
        return $this->interfaces;
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
