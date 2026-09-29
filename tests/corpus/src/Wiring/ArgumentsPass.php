<?php

/**
 * @file
 * A compiler pass that changes a service's arguments after the YAML loads.
 */

declare(strict_types=1);

namespace Drupal\corpus\Wiring;

use Drupal\Core\DependencyInjection\ContainerBuilder;

/**
 * Adds the arguments corpus_wiring.altered leaves out of its definition.
 */
final class ArgumentsPass {

  /**
   * Completes the definition.
   */
  public function process(ContainerBuilder $container): void {
    $container->getDefinition('corpus_wiring.altered')->setArguments([new \stdClass(), new \stdClass()]);
  }

}
