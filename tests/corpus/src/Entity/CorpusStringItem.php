<?php

/**
 * @file
 * A content entity whose ID is a string.
 */

declare(strict_types=1);

namespace Drupal\corpus\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * The interface only the string ID entity implements.
 */
interface CorpusStringItemInterface extends ContentEntityInterface {}

/**
 * Keyed by a machine name, the way workspaces are.
 */
#[ContentEntityType(
  id: 'corpus_string_item',
  entity_keys: ['id' => 'id'],
)]
final class CorpusStringItem extends ContentEntityBase implements CorpusStringItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type): array {
    $fields = parent::baseFieldDefinitions($entity_type);
    $fields['id'] = BaseFieldDefinition::create('string')
      ->setLabel('ID');
    return $fields;
  }

}
