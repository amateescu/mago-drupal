<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * Tells whether a phpcs suppression comment covers an issue of one rule or
 * analyzer check, for the Coder codes that it ports.
 *
 * A comment that names a code drops the issue when the code is one that the
 * rule ports, or the sniff, category or standard of one. A comment without
 * codes drops the issue of every rule, and so does `phpcs:ignoreFile`. A
 * rule that ports several codes can't tell which one an issue is for, so a
 * comment for any of them drops it.
 *
 * @internal
 */
final class PhpcsCommentFilter
{
    /**
     * @param list<string> $codes
     */
    private function __construct(
        private readonly array $codes,
    ) {}

    /**
     * The filter of a rule or check, by its `drupal/` code.
     */
    public static function for(string $code): self
    {
        return new self(CoderCodes::of($code));
    }

    /**
     * Whether a comment covers the issue that starts at the offset.
     */
    public function drops(string $contents, int $offset): bool
    {
        $ignores = PhpcsIgnores::of($contents);

        return $ignores !== null && $ignores->ignores($ignores->line($contents, $offset), $this->codes);
    }
}
