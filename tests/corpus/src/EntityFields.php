<?php

/**
 * @file
 * Magic field properties on a content entity, a field item list and an item.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\CorpusFieldless;
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
   * Checking whether a field is set is not redundant.
   *
   * Field names are not split by entity type, so the name may be no field of
   * this entity, only a plain value that was never set.
   */
  public function fieldIsset(CorpusThing $thing): mixed {
    if (isset($thing->moderation_state) || !empty($thing->body)) {
      return 'set';
    }

    return $thing->field_label ?? 'none';
  }

  /**
   * A field some module or config defines is a field item list.
   *
   * An object is always true, and a field item list has no `__toString()`.
   */
  public function definedField(CorpusThing $thing): string {
    // @mago-expect analysis:redundant-condition
    if ($thing->moderation_state) {
      return 'moderated';
    }

    // @mago-expect analysis:invalid-operand
    return 'Body: ' . $thing->body;
  }

  /**
   * A name no code or config defines a field with is a plain value.
   *
   * So a check on it is not redundant, and a string built from it gets the
   * usual report for a value of unknown type.
   */
  public function adHocProperty(CorpusThing $thing): string {
    if ($thing->in_preview) {
      return 'preview';
    }

    // @mago-expect analysis:mixed-operand
    return 'Password: ' . $thing->pass_raw;
  }

  /**
   * A name no field can have is a plain value of any type.
   */
  public function nonFieldName(CorpusThing $thing): void {
    // @mago-expect analysis:mixed-argument
    $this->wantsList($thing->passRaw);
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
   * A content entity interface has the magic methods at runtime.
   *
   * Every content entity class extends `ContentEntityBase`, so neither the
   * read nor the write is a missing `__get()` or `__set()`.
   */
  public function interfaceField(ContentEntityInterface $entity): void {
    $entity->field_thing->onlyOnFieldItemList();
    $entity->field_thing = 'a plain string';
  }

  /**
   * A class without `__get()` keeps the report, even a fieldable one.
   */
  public function concreteField(CorpusFieldless $entity): mixed {
    // @mago-expect analysis:missing-magic-method
    return $entity->field_thing;
  }

  /**
   * On a config entity interface, `EntityBase` serves `original` too.
   */
  public function interfaceOriginal(ConfigEntityInterface $config): void {
    // @mago-expect analysis:drupal/deprecated-original
    $config->original?->id();
  }

  /**
   * A `@property` tag on another interface still needs the magic method.
   */
  public function taggedInterface(TaggedThing $thing): string {
    // @mago-expect analysis:missing-magic-method
    return $thing->tagged;
  }

  /**
   * The `original` property is the entity before the save, not a field.
   *
   * Every use of it is deprecated: reading, writing, `isset()` and `unset()`.
   */
  public function original(CorpusThing $thing, CorpusThing $other): void {
    // @mago-expect analysis:drupal/deprecated-original
    $thing->original?->id();
    // @mago-expect analysis:drupal/deprecated-original
    $thing->original = $other;
    // @mago-expect analysis:drupal/deprecated-original
    $other /* the entity */->original?->id();
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
 * Documents a property it has no way to serve.
 *
 * @property string $tagged
 */
interface TaggedThing {}

/**
 * Carries entities in declared properties, as core's entity type events do.
 */
final class OriginalHolder {

  public function __construct(
    public readonly EntityInterface $original,
    public readonly EntityInterface $unoriginal,
  ) {}

}
