<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use function trim;

/**
 * One physical line inside a docblock, without its comment markers.
 *
 * @internal
 */
final class DocblockLine
{
    public function __construct(
        public readonly string $text,
        public readonly int $offset,
    ) {}

    /**
     * The lines trimmed and joined with nothing between them, as Coder joins
     * the lines of a short description.
     *
     * @param list<DocblockLine> $lines
     */
    public static function joined(array $lines): string
    {
        $text = '';
        foreach ($lines as $line) {
            $text .= trim($line->text);
        }

        return $text;
    }
}
