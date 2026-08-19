<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;

use function array_map;
use function count;
use function implode;
use function strlen;
use function trim;

/**
 * One `@tag` entry inside a docblock, with its continuation lines attached.
 *
 * `$lines[0]` is the rest of the line the tag name was found on, which is
 * empty when the tag has nothing else on that line. `$lines[1...]` are the
 * lines below it, up to the next tag or the end of the docblock.
 *
 * @internal
 */
final class DocblockTag
{
    /**
     * @param list<DocblockLine> $lines
     */
    public function __construct(
        public readonly string $name,
        public readonly Span $nameSpan,
        public readonly array $lines,
    ) {}

    /**
     * Returns the tag's content, joined across its lines and trimmed.
     */
    public function content(): string
    {
        return trim(implode(' ', array_map(static fn(DocblockLine $line): string => $line->text, $this->lines)));
    }

    /**
     * Returns the span from right after the tag name to the end of its last
     * line, for reporting an issue against the tag's content as a whole.
     */
    public function contentSpan(): Span
    {
        $last = $this->lines[count($this->lines) - 1];

        return new Span($this->lines[0]->offset, $last->offset + strlen($last->text));
    }
}
