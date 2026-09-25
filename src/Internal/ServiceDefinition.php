<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * One container service the compiled container can hand back.
 *
 * @internal
 */
final class ServiceDefinition
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string|null $class Null when the definition never names
     *   the class, such as a factory without `class:`.
     * @param string|null $deprecation The `deprecated` message with its
     *   placeholders filled in, or null when the service is current.
     * @param non-empty-string|null $undecoratedClass The class the id had
     *   before a decorator took it over, or null when nothing decorates it.
     * @param bool $public False when the compiled container leaves the id
     *   out, so `get()` cannot hand it back: a `public: false` definition, or
     *   the `.inner` id of a decorator.
     */
    public function __construct(
        public readonly string $id,
        public readonly ?string $class,
        public readonly ?string $deprecation = null,
        public readonly ?string $undecoratedClass = null,
        public readonly bool $public = true,
    ) {}
}
