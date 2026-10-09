<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Component\Annotation\AnnotationInterface;
use Drupal\Component\Annotation\Plugin;
use Drupal\Component\Annotation\Plugin as AnnotationPlugin;
use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * A config entity, whose properties may use any case.
 */
class PropertyNameConfigEntity extends ConfigEntityBase {

  /**
   * A name in snake_case.
   */
  public string $snake_case = '';

  /**
   * A name in UpperCamelCase.
   */
  public string $UpperCamel = '';

  // @mago-expect lint:drupal/property-name
  /**
   * A leading underscore is still reported.
   */
  public string $_hidden = '';

  /**
   * Returns an object of a class with no name.
   */
  public function settings(): object {
    // The anonymous class follows the class around it.
    return new class() {

      /**
       * A name in snake_case.
       */
      public string $max_length = '';

    };
  }

}

/**
 * A plugin annotation that extends Plugin.
 */
class PropertyNamePlugin extends Plugin {

  /**
   * A name in snake_case.
   */
  public string $plugin_id = '';

}

/**
 * A plugin annotation that implements AnnotationInterface.
 */
class PropertyNameAnnotation implements AnnotationInterface {

  /**
   * A name in snake_case.
   */
  public string $plugin_id = '';

}

/**
 * Extends Plugin through an alias. Coder compares the name as written.
 */
class PropertyNameAliasedPlugin extends AnnotationPlugin {

  // @mago-expect lint:drupal/property-name
  /**
   * A name in snake_case.
   */
  public string $plugin_id = '';

}
