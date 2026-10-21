<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Cache;

use Mago\Sdk\Syntax\SourceFile;

use function preg_match;
use function stripos;

/**
 * A per-file text screen that decides whether a rule can match at all.
 *
 * A gate is correct only for a rule whose every match puts a known piece of
 * text in the source, so a rule that reports a missing thing cannot use
 * one. The result is kept in a FileMemo for the file's text, because the
 * rules see the nodes of each file in sequence.
 *
 * @internal
 */
final class FileGate
{
    /**
     * The text of the last file, compared as FileMemo compares it.
     */
    private ?string $contents = null;

    private bool $passes = false;

    /**
     * @param list<string> $needles Case-insensitive substrings. One hit passes.
     * @param null|string $pattern A regex that is tried when the needles miss.
     */
    public function __construct(
        private readonly array $needles = [],
        private readonly ?string $pattern = null,
    ) {}

    public function passes(SourceFile $file): bool
    {
        // A gate runs for every node of the file, so it checks the last text
        // here instead of through a FileMemo call.
        $contents = $file->contents;
        if ($this->contents !== $contents) {
            $this->contents = $contents;
            $this->passes = $this->scan($contents);
        }

        return $this->passes;
    }

    private function scan(string $contents): bool
    {
        foreach ($this->needles as $needle) {
            if (stripos($contents, $needle) !== false) {
                return true;
            }
        }

        return $this->pattern !== null && preg_match($this->pattern, $contents) === 1;
    }
}
