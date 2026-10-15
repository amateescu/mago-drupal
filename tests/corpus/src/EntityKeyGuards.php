<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Reads entity type keys after a hasKey() check.
 */
final class EntityKeyGuards {

  public function __construct(
    private readonly EntityTypeInterface $entityType,
  ) {}

  /**
   * The checks that guard the call.
   */
  public function guarded(EntityTypeInterface $type, string $key, string $name): string {
    if ($type->hasKey('owner')) {
      $this->field($type->getKey('owner'));
    }
    if ($type->hasKey('published') && $name == $type->getKey('published')) {
      $this->field($name);
    }
    if ($this->entityType->hasKey('label')) {
      $this->field($this->entityType->getKey('label'));
    }
    if ($type->hasKey($key)) {
      $this->field($type->getKey($key));
    }
    if (!$type->hasKey('uuid')) {
      throw new \LogicException('The entity type has no UUID key.');
    }

    return $type->getKey('uuid');
  }

  /**
   * The checks that guard nothing.
   */
  public function unguarded(EntityTypeInterface $type, EntityTypeInterface $other, bool $flag): void {
    if ($type->hasKey('owner')) {
      // @mago-expect analysis:possibly-false-argument
      $this->field($type->getKey('published'));
      // @mago-expect analysis:possibly-false-argument
      $this->field($other->getKey('owner'));
    }
    else {
      // @mago-expect analysis:possibly-false-argument
      $this->field($type->getKey('owner'));
    }
    if ($type->hasKey('owner') || $flag) {
      // @mago-expect analysis:possibly-false-argument
      $this->field($type->getKey('owner'));
    }
    if (!$type->hasKey('owner')) {
      $flag = TRUE;
    }
    // @mago-expect analysis:possibly-false-argument
    $this->field($type->getKey('owner'));
    if ($type->hasKey('owner')) {
      $type = $other;
      // @mago-expect analysis:possibly-false-argument
      $this->field($type->getKey('owner'));
    }
  }

  /**
   * Takes a field name.
   */
  private function field(string $name): void {
  }

}
