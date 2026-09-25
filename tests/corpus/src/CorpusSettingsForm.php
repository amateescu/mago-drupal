<?php

/**
 * @file
 * A config form, whose `config()` helper tags the editable config.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Form\ConfigFormBase;

/**
 * Edits the corpus settings.
 */
final class CorpusSettingsForm extends ConfigFormBase {

  /**
   * Reads a typed key through the form helper.
   */
  public function currentName(): string {
    return $this->config('corpus.settings')->get('name') ?? '';
  }

  /**
   * Unknown keys are reported through the helper as well.
   */
  public function typo(): void {
    // @mago-expect analysis:drupal/config-unknown-key
    $this->config('corpus.settings')->get('nmae');
  }

}
