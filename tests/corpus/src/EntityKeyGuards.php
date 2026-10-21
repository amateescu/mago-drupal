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
    if ($type->hasKey('bundle') === TRUE) {
      $this->field($type->getKey('bundle'));
    }
    if ($type->hasKey('revision') === FALSE) {
      throw new \LogicException('The entity type has no revision key.');
    }
    $this->field($type->getKey('revision'));
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
    // The key is missing in these branches.
    if ($type->hasKey('label') === FALSE) {
      // @mago-expect analysis:possibly-false-argument
      $this->field($type->getKey('label'));
    }
    if (TRUE !== $type->hasKey('label')) {
      // @mago-expect analysis:possibly-false-argument
      $this->field($type->getKey('label'));
    }
    if ($flag) {
      $flag = FALSE;
    }
    elseif (!$type->hasKey('label')) {
      return;
    }
    // The elseif doesn't run when the if does.
    // @mago-expect analysis:possibly-false-argument
    $this->field($type->getKey('label'));
  }

  /**
   * A guard drops the reports about the false, not the ones about the rest.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $type
   *   The entity type.
   * @param list<string> $labels
   *   The labels.
   * @param array<array-key, string> $names
   *   The names.
   */
  public function restOfTheType(EntityTypeInterface $type, array $labels, array $names): int {
    if ($type->hasKey('label')) {
      $names[$type->getKey('label')] = 'label';
      $this->field($names[$type->getKey('label')]);
      // A list doesn't take a string key either.
      // @mago-expect analysis:mismatched-array-index
      $this->field($labels[$type->getKey('label')]);
      // A string isn't an int either.
      // @mago-expect analysis:invalid-return-statement
      return $type->getKey('label');
    }

    return 0;
  }

  /**
   * A guarded string fits a nullable string.
   */
  public function nullableKey(EntityTypeInterface $type): ?string {
    if ($type->hasKey('label')) {
      return $type->getKey('label');
    }

    return NULL;
  }

  /**
   * A literal type that holds the text of a string type is still a literal.
   *
   * @return ')|string|('
   *   The literal.
   */
  public function literalKey(EntityTypeInterface $type): string {
    if ($type->hasKey('label')) {
      // @mago-expect analysis:invalid-return-statement
      return $type->getKey('label');
    }

    return ')|string|(';
  }

  /**
   * The checks that guard a link template.
   */
  public function linkGuarded(EntityTypeInterface $type, string $rel, bool $flag): string {
    if ($type->hasLinkTemplate('canonical')) {
      $this->path($type->getLinkTemplate('canonical'));
    }
    if ($type->hasLinkTemplate('add-form') && str_contains($type->getLinkTemplate('add-form'), '{')) {
      $this->path('/add');
    }
    if ($this->entityType->hasLinkTemplate('collection')) {
      $this->path($this->entityType->getLinkTemplate('collection'));
    }
    if ($type->hasLinkTemplate($rel)) {
      $this->path($type->getLinkTemplate($rel));
    }
    $pinned = $flag && $type->hasLinkTemplate('restore') && str_contains($type->getLinkTemplate('restore'), '{');
    if ($pinned) {
      $this->path('/restore');
    }
    if (!$type->hasLinkTemplate('edit-form')) {
      throw new \LogicException('The entity type has no edit form.');
    }

    return $type->getLinkTemplate('edit-form');
  }

  /**
   * The link template checks that guard nothing.
   */
  public function linkUnguarded(EntityTypeInterface $type, EntityTypeInterface $other, bool $flag): bool {
    if ($type->hasLinkTemplate('canonical')) {
      // @mago-expect analysis:possibly-false-argument
      $this->path($type->getLinkTemplate('edit-form'));
      // @mago-expect analysis:possibly-false-argument
      $this->path($other->getLinkTemplate('canonical'));
    }
    if (!$type->hasLinkTemplate('canonical')) {
      $flag = TRUE;
    }
    // @mago-expect analysis:possibly-false-argument
    $this->path($type->getLinkTemplate('canonical'));
    // @mago-expect analysis:possibly-false-argument
    $either = $type->hasLinkTemplate('canonical') || str_contains($type->getLinkTemplate('canonical'), '{');
    // @mago-expect analysis:possibly-false-argument
    $negated = !$type->hasLinkTemplate('canonical') && str_contains($type->getLinkTemplate('canonical'), '{');
    // @mago-expect analysis:possibly-false-argument
    $wrapped =
      $this->check($type->hasLinkTemplate('canonical')) && str_contains($type->getLinkTemplate('canonical'), '{');
    // @mago-expect analysis:possibly-false-argument
    $listed = [$flag && $type->hasLinkTemplate('canonical'), str_contains($type->getLinkTemplate('canonical'), '{')];

    return $either && $negated && $wrapped && $listed[0];
  }

  /**
   * Takes a field name.
   */
  private function field(string $name): void {
  }

  /**
   * Takes a path.
   */
  private function path(string $path): void {
  }

  /**
   * Hands a flag back.
   */
  private function check(bool $flag): bool {
    return $flag;
  }

}
