<?php

/**
 * @file
 * A controller, whose base does not compose DependencySerializationTrait.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Controller\ControllerBase;
use Drupal\corpus\Nested\Thing;

/**
 * Keeps a private service, which the trait never has to restore here.
 */
final class SerializationController extends ControllerBase {

  /**
   * Private is fine without the trait.
   */
  public function __construct(
    private readonly Thing $thing,
  ) {}

  /**
   * Reads the property so it is not write-only.
   */
  public function thing(): Thing {
    return $this->thing;
  }

}
