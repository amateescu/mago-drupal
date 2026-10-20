<?php

declare(strict_types=1);

namespace Drupal\ignores;

/**
 * Render callbacks under PHPStan comments, which --phpstan-ignores reads.
 */
final class PhpstanComments {

  /**
   * Returns callbacks that core doesn't trust.
   */
  public function build(): array {
    return [
      // @phpstan-ignore-next-line
      '#pre_render' => ['ignores_pre_render'],
      // @phpstan-ignore renderCallback.nonTrustedCallback
      '#post_render' => ['ignores_post_render'],
      // @mago-expect lint:drupal/render-callback
      // @phpstan-ignore argument.type
      '#access_callback' => 'ignores_access',
    ];
  }

}
