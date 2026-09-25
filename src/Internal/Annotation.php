<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_key_exists;

/**
 * One parsed `@Name(...)` annotation.
 *
 * @internal
 */
final class Annotation
{
    /**
     * @param array<array-key, mixed> $arguments Named and positional values.
     */
    public function __construct(
        public readonly string $name,
        public readonly array $arguments,
    ) {}

    /**
     * @return non-empty-string|null
     */
    public function string(string $key): ?string
    {
        return Shape::nonEmptyString($this->arguments[$key] ?? null);
    }

    /**
     * @return array<array-key, mixed>
     */
    public function map(string $key): array
    {
        return Shape::array($this->arguments[$key] ?? null) ?? [];
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->arguments);
    }
}
