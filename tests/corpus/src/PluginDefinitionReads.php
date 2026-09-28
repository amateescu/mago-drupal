<?php

/**
 * @file
 * Plugin definitions, where they are arrays and where they may be objects.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Layout\LayoutInterface;

/**
 * A plugin whose definitions are arrays, as most plugin types are.
 */
abstract class PluginDefinitionReads extends PluginBase {

  /**
   * Reads definition keys through the property and the getter.
   */
  public function keys(): array {
    return [$this->pluginDefinition['label'], $this->getPluginDefinition()['id']];
  }

  /**
   * The same read through a variable.
   */
  public function held(): mixed {
    $definition = $this->getPluginDefinition();
    return $definition['label'];
  }

}

/**
 * A layout plugin, whose definition is an object.
 */
abstract class CorpusLayout extends PluginBase implements LayoutInterface {

  /**
   * Reading it as an array is a real error here.
   */
  public function keys(): array {
    // @mago-expect analysis:invalid-array-access
    return [$this->pluginDefinition['label']];
  }

}

/**
 * Reads the definitions of plugins handed in.
 */
final class PluginDefinitionArguments {

  /**
   * A block's definition is an array, also through a variable.
   */
  public function blockLabel(BlockPluginInterface $block): mixed {
    $definition = $block->getPluginDefinition();
    return $definition['admin_label'];
  }

  /**
   * A plugin of any type may have a definition object.
   */
  public function anyLabel(PluginInspectionInterface $plugin): mixed {
    // @mago-expect analysis:invalid-array-access
    return $plugin->getPluginDefinition()['label'];
  }

}

/**
 * Reads the definition of the block it is used in.
 */
trait CorpusBlockLabelTrait {

  /**
   * Every class using the trait is a block, so the definition is an array.
   */
  public function adminLabel(): mixed {
    return $this->getPluginDefinition()['admin_label'];
  }

}

/**
 * A block that gets its label from the trait.
 */
final class CorpusTraitBlock extends BlockBase {

  use CorpusBlockLabelTrait;

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return [];
  }

}

/**
 * Reads the definition of a plugin, and a layout uses it too.
 */
trait CorpusAnyPluginLabelTrait {

  /**
   * A layout's definition is an object.
   */
  public function anyLabel(): mixed {
    // @mago-expect analysis:invalid-array-access
    return $this->getPluginDefinition()['label'];
  }

}

/**
 * A plugin that names no plugin type.
 */
final class CorpusAnyPlugin extends PluginBase {

  use CorpusAnyPluginLabelTrait;

}

/**
 * A layout using the same trait.
 */
abstract class CorpusTraitLayout extends PluginBase implements LayoutInterface {

  use CorpusAnyPluginLabelTrait;

}
