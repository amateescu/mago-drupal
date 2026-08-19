<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\SourceFile;

use function preg_match;
use function stripos;

/**
 * A per-file text screen deciding whether a rule can match at all.
 *
 * A gate is sound only for rules whose every match puts a known piece of
 * text in the source, so a rule that reports something missing cannot use
 * one. The result is cached for the file the worker is currently on, since
 * rules see each file's nodes back to back.
 *
 * @internal
 */
final class FileGate
{
    private string $path = '';

    private bool $passes = true;

    /**
     * @param list<string> $needles Case-insensitive substrings; any hit passes.
     * @param null|string $pattern Regex fallback tried after the needles miss.
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
