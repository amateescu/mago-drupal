<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use Mago\Sdk\Span;

use function array_map;
use function array_slice;
use function count;
use function implode;
use function strlen;
use function strspn;
use function substr;
use function trim;

/**
 * One `@tag` entry inside a docblock, with its continuation lines.
 *
 * `$lines[0]` is the rest of the line that holds the tag name. It is empty if
 * the tag doesn't have anything else on that line. `$lines[1...]` are the lines
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

    /**
     * Returns the text on the lines below the tag's own line, each line
     * trimmed, joined with nothing between them.
     */
    public function textBelow(): string
    {
        $text = '';
        foreach (array_slice($this->lines, offset: 1) as $line) {
            $text .= trim($line->text);
        }

        return $text;
    }

    /**
     * Returns the position of this tag in $tags.
     *
     * @param list<DocblockTag> $tags
     */
    public function indexIn(array $tags): ?int
    {
        foreach ($tags as $index => $candidate) {
            if ($candidate === $this) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Returns the offset of the type, or null when the type does not start
     * the tag's line.
     */
    public function typeStart(string $type): ?int
    {
        $line = $this->lines[0];
        $indent = strspn($line->text, characters: " \t");

        return substr($line->text, $indent, strlen($type)) === $type ? $line->offset + $indent : null;
    }
}
