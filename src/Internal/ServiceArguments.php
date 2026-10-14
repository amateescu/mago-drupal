<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * The constructor arguments the container passes to one service's class.
 *
 * @internal
 */
final class ServiceArguments
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $file The services file that defines the id,
     *   such as `node.services.yml`.
     * @param int<0, max> $count
     */
    public function __construct(
        public readonly string $id,
        public readonly string $file,
        public readonly int $count,
    ) {}
}
