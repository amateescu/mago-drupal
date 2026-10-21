<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use function array_intersect;
use function is_array;

/**
 * Tells whether a `@phpstan-ignore` comment covers an issue of a linter rule
 * that ports a PHPStan check.
 *
 * An identifier drops the issue when the rule ports it, and
 * `@phpstan-ignore-line` and `@phpstan-ignore-next-line` drop it whatever
 * follows them. `PHPStanIgnores` finds the line each comment covers.
 *
 * @internal
 */
final class PHPStanCommentFilter
{
    /**
     * @param non-empty-list<string> $identifiers
     */
    private function __construct(
        private readonly array $identifiers,
    ) {}

    /**
     * The filter of a linter rule, by its code, or null when the rule
     * doesn't port a PHPStan check.
     */
    public static function for(string $code): ?self
    {
        $identifiers = PHPStanPorts::of($code);

        return $identifiers === null ? null : new self($identifiers);
    }

    /**
     * Whether a comment covers the issue that starts at the offset.
     */
    public function drops(string $contents, int $offset): bool
    {
        $ignores = PHPStanIgnores::of($contents);
        $ignored = $ignores?->at($offset);

        return $ignored === true || is_array($ignored) && array_intersect($ignored, $this->identifiers) !== [];
    }
}
