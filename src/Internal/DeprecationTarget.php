<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use InvalidArgumentException;

use function preg_match;

/**
 * The Drupal major a project is getting ready for.
 *
 * A deprecation Drupal removes in a later major can wait, so it is not
 * reported. Text that names no Drupal removal version is always reported: a
 * contrib module's own deprecations and Symfony's say nothing about when
 * Drupal drops them.
 *
 * @internal
 */
final class DeprecationTarget
{
    private const REMOVAL = '/\bremoved from drupal:(\d+)\b/';

    private function __construct(
        private readonly ?int $major,
    ) {}

    /**
     * Reports every deprecation.
     */
    public static function all(): self
    {
        return new self(null);
    }

    /**
     * Reports the deprecations removed in the given major or earlier.
     *
     * @throws InvalidArgumentException
     */
    public static function major(int $major): self
    {
        if ($major < 1) {
            throw new InvalidArgumentException("A Drupal major version is a positive number, got {$major}.");
        }

        return new self($major);
    }

    public function isAll(): bool
    {
        return $this->major === null;
    }

    /**
     * Whether a deprecation with this text is reported.
     */
    public function keeps(string $text): bool
    {
        $matches = [];
        if ($this->major === null || preg_match(self::REMOVAL, $text, $matches) !== 1) {
            return true;
        }

        return (int) $matches[1] <= $this->major;
    }
}
