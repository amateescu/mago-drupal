<?php

declare(strict_types=1);

namespace Drupal\ignores;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\user\Entity\User;

/**
 * Static calls under phpcs comments, in a class that can take services.
 */
final class PhpcsStaticCalls implements ContainerInjectionInterface {

  /**
   * A comment for the sniff drops the `\Drupal` call under it.
   */
  public function service(): object {
    // phpcs:ignore DrupalPractice.Objects.GlobalDrupal
    $ignored = \Drupal::service('ignored');
    // @mago-expect analysis:drupal/global-drupal-call
    $reported = \Drupal::service('reported');

    return $ignored === $reported ? $ignored : $reported;
  }

  /**
   * A comment for the sniff drops the entity load under it.
   */
  public function entity(): ?User {
    // phpcs:ignore DrupalPractice.Objects.GlobalClass
    $ignored = User::load(1);
    // @mago-expect analysis:drupal/static-entity-load
    $reported = User::load(2);

    return $ignored ?? $reported;
  }

}
