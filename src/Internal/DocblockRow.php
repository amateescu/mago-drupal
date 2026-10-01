<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;

use function preg_match;
use function strcspn;
use function strlen;
use function strspn;
use function substr;

/**
 * One physical line of a docblock, with the offsets of its parts.
 *
 * The offsets are absolute. `$start` is where the line begins, or the `/**`
 * on the first line. `$star` is the leading star, or null on the first line,
 * on the line of a lone `*\/` and on a line without one. `$textStart` and
 * `$textEnd` bound the text after the star and its spaces, before trailing
 * whitespace and before `*\/`. An empty text has both at the same offset.
 *
 * @internal
 */
final class DocblockRow
{
    /**
     * Whether the text is a directive, its tag, and its value, read on first
     * use.
     *
     * @var array{bool, ?string, string}|null
     */
    private ?array $parts = null;

    public function __construct(
        public readonly int $start,
        public readonly ?int $star,
        public readonly int $textStart,
        public readonly int $textEnd,
        public readonly string $text,
    ) {}

    public function isBlank(): bool
    {
        return $this->text === '';
    }

    /**
     * Whether the line holds prose: text that is not a tag or a directive.
     */
    public function isProse(): bool
    {
        return !$this->isBlank() && $this->tag() === null && !$this->isDirective();
    }

    /**
     * Where the text starts, counted from the start of the line.
     */
    public function column(): int
    {
        return $this->textStart - $this->start;
    }

    public function textSpan(): Span
    {
        return new Span($this->textStart, $this->textEnd);
    }

    public function tagSpan(): Span
    {
        return new Span($this->textStart, $this->textStart + strlen((string) $this->tag()));
    }

    /**
     * Whether the text is a `phpcs:` instruction. phpcs takes it for neither
     * text nor a tag.
     */
    public function isDirective(): bool
    {
        return $this->parts()[0];
    }

    /**
     * The tag that starts the text, such as `@param`, as phpcs reads it: an
     * `@` and every character up to the next whitespace. Null for a line of
     * prose or a directive.
     */
    public function tag(): ?string
    {
        return $this->parts()[1];
    }

    /**
     * The text after the tag and the whitespace after it, or the whole text
     * of a line with no tag. Empty for a tag with nothing after it on the
     * line.
     */
    public function value(): string
    {
        return $this->parts()[2];
    }

    /**
     * @return array{bool, ?string, string}
     */
    private function parts(): array
    {
        if ($this->parts !== null) {
            return $this->parts;
        }

        $first = $this->text[0] ?? '';
        $directive =
            ($first === '@' || $first === 'p' || $first === 'P') && preg_match('/^@?phpcs:/i', $this->text) === 1;
        $length = $first === '@' && !$directive ? 1 + strcspn($this->text, characters: " \t", offset: 1) : 0;
        $tag = $length > 1 ? substr($this->text, offset: 0, length: $length) : null;
        $rest = substr($this->text, $length);
        $value = match (true) {
            $directive => '',
            $tag === null => $this->text,
            default => substr($rest, strspn($rest, characters: " \t")),
        };

        return $this->parts = [$directive, $tag, $value];
    }
}
