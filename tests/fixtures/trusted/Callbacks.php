<?php

namespace Drupal\fixture;

use Drupal\Core\Security\Attribute\TrustedCallback;
use Drupal\Core\Security\TrustedCallbackInterface;

class Imported {

  #[TrustedCallback]
  public static function preRender(array $element): array {
    return $element;
  }

}

final class FullyQualified {

  /**
   * An attribute in front, another after it.
   */
  #[\Override]
  #[\Drupal\Core\Security\Attribute\TrustedCallback]
  #[Other]
  public function &lazyBuilder(): array {
    static $build = [];
    return $build;
  }

}

class Listed implements TrustedCallbackInterface {

  public static function trustedCallbacks(): array {
    return ['notAttributed'];
  }

  public static function notAttributed(): void {
  }

}

$anonymous = new class {

  #[TrustedCallback]
  public function inAnonymous(): void {
  }

};

trait Shared {

  #[TrustedCallback]
  public static function fromTrait(array $element): array {
    return $element;
  }

}
