<?php

/**
 * @file
 * A base field this module adds to every entity type.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep\Hook;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Defines the `moderation_state` field the way content_moderation does.
 */
final class DepFieldHooks
{
    /**
     * Implements hook_entity_base_field_info().
     */
    #[Hook('entity_base_field_info')]
    public function entityBaseFieldInfo(EntityTypeInterface $entityType): array
    {
        $fields = [];
        $fields['moderation_state'] = BaseFieldDefinition::create('string')->setComputed(true);

        return $fields;
    }
}
