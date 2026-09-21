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
 * One `@tag` entry inside a docblock, with its continuation lines.
 *
 * `$lines[0]` is the rest of the line that holds the tag name. It is empty
 * if the tag has nothing else on that line. `$lines[1...]` are the lines
 * below it, up to the next tag or the end of the docblock.
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
     * Returns the tag's content. Its lines are joined and trimmed.
     */
    private ?string $content = null;

    public function content(): string
    {
        return $this->content ??= trim(implode(' ', array_map(
            static fn(DocblockLine $line): string => $line->text,
            $this->lines,
        )));
    }

    /**
     * Returns the span from the end of the tag name to the end of its last
     * line. Use it to report an issue against the tag's whole content.
     */
    public function contentSpan(): Span
    {
        $last = $this->lines[count($this->lines) - 1];

        return new Span($this->lines[0]->offset, $last->offset + strlen($last->text));
    }
}
