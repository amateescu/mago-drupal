<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

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
}
