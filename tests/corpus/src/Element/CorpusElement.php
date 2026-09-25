<?php

/**
 * @file
 * A render element declared with a positional-id annotation.
 */

declare(strict_types=1);

namespace Drupal\corpus\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * The corpus render element; PluginID annotations carry the id unnamed.
 *
 * @RenderElement("corpus_element")
 */
final class CorpusElement extends RenderElementBase {

  /**
   * Only exists here, so a call to it proves the plugin class.
   */
  public function onlyOnCorpusElement(): void {
  }

}
