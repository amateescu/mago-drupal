<?php

/**
 * @file
 * A class that uses the config form trait without extending the form base.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Config\ConfigBase;
use Drupal\Core\Form\ConfigFormBaseTrait;

/**
 * Reads settings the way a list builder using the trait does.
 */
final class CorpusTraitUser {

  use ConfigFormBaseTrait;

  /**
   * The trait's helper tags the config wherever the trait is used.
   */
  public function currentName(): string {
    return $this->config('corpus.settings')->get('name') ?? '';
  }

  /**
   * The whole object is an array, and a named key argument works too.
   *
   * @return array<string, mixed>
   */
  public function everything(): array {
    $count = $this->config('corpus.settings')->get(key: 'count') ?? 0;

    return $count > 0 ? $this->config('corpus.settings')->get() : [];
  }

  /**
   * A config handed over as the base class carries no name.
   */
  public function untagged(ConfigBase $config): string {
    // @mago-expect analysis:mixed-return-statement
    return $config->get('name');
  }

}
