<?php

/**
 * @file
 * Classes that take services without a container interface of their own.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Entity\EntityListBuilder;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A list builder, which gets the container through createInstance().
 */
class ReachListBuilder extends EntityListBuilder {

  /**
   * Reads state through the global container.
   */
  public function rows(): mixed {
    // @mago-expect analysis:drupal/global-drupal-call
    return \Drupal::state();
  }

}

/**
 * A deriver, which gets the container through create().
 */
final class ReachDeriver implements ContainerDeriverInterface {

  /**
   * Builds the deriver.
   */
  public static function create(ContainerInterface $container, $base_plugin_id): static {
    return new static();
  }

  /**
   * Reads state through the global container.
   */
  public function definitions(): mixed {
    // @mago-expect analysis:drupal/global-drupal-call
    return \Drupal::state();
  }

}

/**
 * A block, which takes services once it adds create().
 */
abstract class ReachBlock extends BlockBase {

  /**
   * Reads state through the global container.
   */
  public function state(): mixed {
    // @mago-expect analysis:drupal/global-drupal-call
    return \Drupal::state();
  }

}

/**
 * A class that a services file registers.
 */
class ReachService {

  /**
   * Reads state through the global container.
   */
  public function value(): mixed {
    // @mago-expect analysis:drupal/global-drupal-call
    return \Drupal::state();
  }

}

/**
 * A plain object, which nothing builds with services.
 */
class ReachPlain {

  /**
   * Reads state through the global container.
   */
  public function value(): mixed {
    return \Drupal::state();
  }

}
