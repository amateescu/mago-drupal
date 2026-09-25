<?php

/**
 * @file
 * Magic field properties on a content entity, a field item list and an item.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\corpus\Entity\CorpusSetting;
use Drupal\corpus\Entity\CorpusThing;

/**
 * Exercises the magic property providers.
 */
final class EntityFields {

  /**
   * An undeclared property on a content entity is a field item list.
   */
  public function magicField(CorpusThing $thing): void {
    $thing->field_thing->onlyOnFieldItemList();
    $this->wantsList($thing->field_thing);
    // @mago-expect analysis:non-existent-method
    $thing->field_thing->notOnFieldItemList();
  }

  /**
   * Writing a field takes any value, the way `__set()` does.
   */
  public function writeField(CorpusThing $thing): void {
    $thing->field_thing = 'a plain string';
  }

  /**
   * A declared property and a `@property` tag keep their own type.
   */
  public function declaredWins(CorpusThing $thing): void {
    // @mago-expect analysis:invalid-argument
    $this->wantsList($thing->weight);
    // @mago-expect analysis:invalid-argument
    $this->wantsList($thing->taggedCount);
  }

  /**
   * A field item list forwards any property to its first item.
   */
  public function itemProperty(CorpusThing $thing): void {
    // @mago-expect analysis:mixed-argument
    $this->wantsList($thing->field_thing->value);
  }

  /**
   * A field item takes any property its field type defines.
   */
  public function fieldItemProperty(FieldItemInterface $item): void {
    // @mago-expect analysis:mixed-argument
    $this->wantsList($item->value);
    $item->value = 'a plain string';
  }

  /**
   * `original` is the entity before the save, not a field.
   *
   * Every use of it is deprecated: reading, writing, `isset()` and `unset()`.
   */
  public function original(CorpusThing $thing, CorpusThing $other): void {
    // @mago-expect analysis:drupal/deprecated-original
    $thing->original?->id();
    // @mago-expect analysis:drupal/deprecated-original
    $thing->original = $other;
  }

  /**
   * The checks that never read the value are deprecated as well.
   */
  public function originalChecks(CorpusThing $thing, CorpusThing $other): EntityInterface {
    // @mago-expect analysis:drupal/deprecated-original
    if (isset($thing->original)) {
      // @mago-expect analysis:drupal/deprecated-original
      unset($thing->original);
    }

    // @mago-expect analysis:drupal/deprecated-original
    return $other->original ?? $other;
  }

  /**
   * A second access to the same receiver is reported too.
   *
   * Mago reuses the narrowed type there instead of analyzing it again.
   */
  public function originalAgain(CorpusThing $thing): ?string {
    // @mago-expect analysis:drupal/deprecated-original
    if (!empty($thing->original)) {
      // @mago-expect analysis:drupal/deprecated-original
      return $thing->original->label();
    }

    return NULL;
  }

  /**
   * Config entities and receivers that may be null reach it too.
   */
  public function originalElsewhere(CorpusSetting $setting, ?CorpusThing $thing): void {
    // @mago-expect analysis:drupal/deprecated-original
    $setting->original?->id();
    // @mago-expect analysis:drupal/deprecated-original
    $thing?->original?->id();
  }

  /**
   * A declared `$original` outside an entity is an ordinary property.
   */
  public function declaredOriginal(OriginalHolder $holder): void {
    $holder->original->id();
    $holder->unoriginal->id();
  }

  /**
   * Takes a field item list, so a wrong type shows up as an argument error.
   */
  private function wantsList(FieldItemListInterface $list): void {
    $list->onlyOnFieldItemList();
  }

}

/**
 * Carries entities in declared properties, as core's entity type events do.
 */
final class OriginalHolder {

  public function __construct(
    public readonly EntityInterface $original,
    public readonly EntityInterface $unoriginal,
  ) {}

}
