<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function preg_match;
use function stripos;

/**
 * A per-file text screen that decides whether a rule can match at all.
 *
 * A gate is correct only for a rule whose every match puts a known piece of
 * text in the source, so a rule that reports a missing thing cannot use
 * one. The result is cached for the worker's current file, because the
 * rules see the nodes of each file in sequence.
 *
 * @internal
 */
final class FileGate
{
    private string $path = '';

    private bool $passes = true;

    /**
     * @param list<string> $needles Case-insensitive substrings. One hit passes.
     * @param null|string $pattern A regex that is tried if no needle hits.
     */
    public function __construct(
        private readonly array $needles = [],
        private readonly ?string $pattern = null,
    ) {}

    public function passes(SourceFile $file): bool
    {
        if ($this->path === $file->path) {
            return $this->passes;
        }

        $this->path = $file->path;

        foreach ($this->needles as $needle) {
            if (stripos($file->contents, $needle) !== false) {
                return $this->passes = true;
            }
        }

        return $this->passes = $this->pattern !== null && preg_match($this->pattern, $file->contents) === 1;
    }
}
