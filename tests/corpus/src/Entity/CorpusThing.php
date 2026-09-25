<?php

/**
 * @file
 * A content entity type declared with an attribute in a scanned file.
 */

declare(strict_types=1);

namespace Drupal\corpus\Entity;

use Drupal\Core\Entity\Attribute\ContentEntityType;
use Drupal\Core\Entity\ContentEntityBase;

/**
 * The corpus content entity, with custom storage, access and form handlers.
 *
 * @property int $taggedCount
 */
#[ContentEntityType(
  id: 'corpus_thing',
  handlers: [
    'storage' => CorpusThingStorage::class,
    'access' => CorpusThingAccessControlHandler::class,
    'form' => [
      'default' => CorpusThingForm::class,
    ],
  ],
  entity_keys: ['id' => 'id'],
)]
final class CorpusThing extends ContentEntityBase {

  /**
   * A real property, which the magic field provider must not shadow.
   */
  public int $weight = 0;

  /**
   * Only exists here, so a call to it proves the receiver type.
   */
  public function onlyOnThing(): void {
  }

}
