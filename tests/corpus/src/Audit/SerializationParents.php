<?php

/**
 * @file
 * Private properties of the parents of a class composing the trait.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\State\StateInterface;
use Drupal\corpus\Nested\CrossTrait;
use Drupal\corpus_dep\NestedSerializationTrait;
use Symfony\Component\HttpFoundation\Session\Storage\Handler\AbstractSessionHandler;
use Vendor\Library\KeepsCache;

/**
 * A grandparent with a private property.
 */
abstract class SerializedGrandparent {

  /**
   * Out of reach of a descendant's get_object_vars().
   */
  private int $depth = 0;

  /**
   * Reads the property so it is not unused.
   */
  public function depth(): int {
    return $this->depth;
  }

}

/**
 * A parent with private properties, above every class using the trait.
 */
abstract class SerializedParent extends SerializedGrandparent {

  /**
   * Out of reach of a child's get_object_vars().
   */
  private string $label = '';

  /**
   * Shared by every instance, so serialization never carries it.
   */
  private static int $count = 0;

  /**
   * Visible to a child's get_object_vars().
   */
  protected string $visible = '';

  /**
   * Reads the properties so they are not unused.
   */
  public function summary(): string {
    return $this->label . self::$count . $this->visible;
  }

}

/**
 * Composes the trait below the parents that declare private properties.
 */
// @mago-expect analysis:drupal/dependency-serialization-property(2)
class SerializedChild extends SerializedParent {

  use DependencySerializationTrait;

}

/**
 * Composes the trait through a trait that uses it.
 */
// @mago-expect analysis:drupal/dependency-serialization-property(2)
final class NestedSerializedChild extends SerializedParent {

  use NestedSerializationTrait;

}

/**
 * Its parent composes the trait and reports the grandparents' properties.
 */
final class SerializedGrandchild extends SerializedChild {
  // A private property here would be a report of its own.
}

/**
 * Composes the trait again, below a parent that composes it already.
 */
final class RecomposedChild extends SerializedChild {

  // The parent reports the private properties above it.
  use DependencySerializationTrait;

}

/**
 * Lists what to serialize itself, so the trait's __sleep() does not run.
 */
final class SleepingChild extends SerializedParent {

  use DependencySerializationTrait;

  /**
   * {@inheritdoc}
   */
  public function __sleep(): array {
    return ['visible'];
  }

}

/**
 * A parent that serializes through __serialize(), which PHP prefers.
 */
abstract class SelfSerializingParent {

  /**
   * Carried by __serialize().
   */
  private string $state = '';

  /**
   * Serializes the state.
   */
  public function __serialize(): array {
    return ['state' => $this->state];
  }

  /**
   * Restores the state.
   *
   * @param array{state: string} $data
   *   What __serialize() returned.
   */
  public function __unserialize(array $data): void {
    $this->state = $data['state'];
  }

}

/**
 * Its parent's __serialize() takes over from the trait's __sleep().
 */
final class SelfSerializingChild extends SelfSerializingParent {

  use DependencySerializationTrait;

}

/**
 * Gets a private property from a trait in Drupal's namespace.
 */
abstract class TraitPrivateParent {

  use CrossTrait;

}

/**
 * Its parent's private property comes from a trait, and is still private.
 */
// @mago-expect analysis:drupal/dependency-serialization-property
final class TraitPrivateChild extends TraitPrivateParent {

  use DependencySerializationTrait;

}

/**
 * Gets a private property from a trait outside Drupal's namespace.
 */
abstract class VendorTraitParent {

  use KeepsCache;

}

/**
 * The vendor trait's private property is not the module's to change.
 */
final class VendorTraitChild extends VendorTraitParent {

  use DependencySerializationTrait;

}

/**
 * Keeps a service in a readonly property.
 */
abstract class ReadonlyServiceParent {

  public function __construct(
    protected readonly StateInterface $state,
  ) {}

}

/**
 * The trait cannot write the parent's readonly property before PHP 8.4.
 */
// @mago-expect analysis:drupal/dependency-serialization-property
final class ReadonlyServiceChild extends ReadonlyServiceParent {

  use DependencySerializationTrait;

}

/**
 * A Symfony parent's private properties are not the module's to change.
 */
final class VendorSerializedChild extends AbstractSessionHandler {

  use DependencySerializationTrait;

}
