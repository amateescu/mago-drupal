<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

/**
 * One entity type as declared by its attribute, default handlers filled in.
 *
 * @internal
 */
final class EntityTypeDefinition
{
    /**
     * @param non-empty-string $id
     * @param non-empty-string $class
     * @param array<non-empty-string, non-empty-string> $handlers Handler type
     *   to class, `form` operations keyed as `form.<operation>`.
     * @param bool $exportsConfig Whether a config entity type lists its
     *   exported properties in `config_export`.
     */
    public function __construct(
        public readonly string $id,
        public readonly string $class,
        public readonly EntityTypeKind $kind,
        public readonly array $handlers,
        public readonly bool $exportsConfig = true,
    ) {}

    /**
     * @return non-empty-string|null
     */
    public function handler(string $type, ?string $operation = null): ?string
    {
        return $this->handlers[$operation === null ? $type : $type . '.' . $operation] ?? null;
    }

    /**
     * @return non-empty-string|null
     */
    public function storage(): ?string
    {
        return $this->handler('storage');
    }

    /**
     * The entity type definition interface `getDefinition()` narrows to, or
     * null for a bare `#[EntityType]`, which has no more specific interface.
     *
     * @return non-empty-string|null
     */
    public function definitionInterface(): ?string
    {
        return match ($this->kind) {
            EntityTypeKind::Content => 'Drupal\Core\Entity\ContentEntityTypeInterface',
            EntityTypeKind::Config => 'Drupal\Core\Config\Entity\ConfigEntityTypeInterface',
            EntityTypeKind::Base => null,
        };
    }
}
