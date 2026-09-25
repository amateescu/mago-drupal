<?php

/**
 * @file
 * A content entity type declared with a legacy docblock annotation.
 */

declare(strict_types=1);

namespace Drupal\corpus\Entity;

use Drupal\Core\Entity\ContentEntityBase;

/**
 * The legacy corpus entity, still on annotations.
 *
 * @ContentEntityType(
 *   id = "corpus_legacy",
 *   label = @Translation("Legacy thing"),
 *   handlers = {
 *     "storage" = "Drupal\corpus\Entity\CorpusLegacyStorage",
 *     "form" = {
 *       "default" = "Drupal\corpus\Entity\CorpusThingForm",
 *     },
 *   },
 *   entity_keys = {
 *     "id" = "id",
 *   },
 * )
 */
final class CorpusLegacy extends ContentEntityBase {

  /**
   * Only exists here, so a call to it proves the receiver type.
   */
  public function onlyOnLegacy(): void {
  }

}
