<?php

/**
 * @file
 * Controllers and plain forms, whose `config()` helper tags immutable config.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBase;

/**
 * Reads the corpus settings from a controller.
 */
final class CorpusSettingsController extends ControllerBase {

  /**
   * Core documents the editable class; the helper hands out an immutable one.
   */
  public function settings(): ImmutableConfig {
    return $this->config('corpus.settings');
  }

  /**
   * Reads a typed key through the controller helper.
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

/**
 * Reads the corpus settings from a form that is not a config form.
 */
final class CorpusPlainForm extends FormBase {

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
