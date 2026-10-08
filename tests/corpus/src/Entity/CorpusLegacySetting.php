<?php

/**
 * @file
 * Config entity types declared with legacy docblock annotations.
 */

declare(strict_types=1);

namespace Drupal\corpus\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Exports its properties explicitly.
 *
 * @ConfigEntityType(
 *   id = "corpus_legacy_setting",
 *   label = @Translation("Legacy setting"),
 *   config_prefix = "legacy",
 *   config_export = {
 *     "id",
 *     "label",
 *   },
 * )
 */
final class CorpusLegacySetting extends ConfigEntityBase {

  /**
   * Only exists here, so a call to it proves the receiver type.
   */
  public function onlyOnLegacySetting(): void {
  }

}

/**
 * Leaves export to reflection.
 *
 * @ConfigEntityType(
 *   id = "corpus_legacy_unexported",
 *   label = @Translation("Unexported legacy setting"),
 * )
 */
// @mago-expect analysis:drupal/config-entity-export
final class CorpusLegacyUnexported extends ConfigEntityBase {}
