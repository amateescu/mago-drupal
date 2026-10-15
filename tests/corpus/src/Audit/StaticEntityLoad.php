<?php

/**
 * @file
 * Static entity loads in classes that can take the entity type manager.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\corpus\Entity\CorpusThing;
use Drupal\user\Entity\User;

/**
 * Loads entities statically in a form, which can take services.
 */
final class StaticEntityLoadForm extends FormBase {

  /**
   * Loads one entity by its class.
   */
  public function one(): ?User {
    // @mago-expect analysis:drupal/static-entity-load(1)
    return User::load(1);
  }

  /**
   * Loads several entities by their class.
   *
   * @return \Drupal\user\Entity\User[]
   *   The users.
   */
  public function several(): array {
    // @mago-expect analysis:drupal/static-entity-load(1)
    return User::loadMultiple([1, 2]);
  }

  /**
   * A class written in full is the same call.
   */
  public function fullName(): ?User {
    // @mago-expect analysis:drupal/static-entity-load(1)
    // @mago-expect lint:drupal/fully-qualified-name
    return \Drupal\user\Entity\User::load(1);
  }

  /**
   * An entity class outside core is the same call.
   */
  public function moduleEntity(): ?CorpusThing {
    // @mago-expect analysis:drupal/static-entity-load(1)
    return CorpusThing::load(1);
  }

  /**
   * A first-class callable doesn't load anything yet.
   */
  public function callable(): \Closure {
    return User::load(...);
  }

  /**
   * A static method has no injected service to use.
   */
  public static function fromStatic(): ?User {
    return User::load(1);
  }

  /**
   * Code on its way out keeps its lookup.
   *
   * @deprecated in corpus:1.0.0 and is removed from corpus:2.0.0. Use one()
   *   instead.
   *
   * @see https://www.drupal.org/node/1
   */
  public function oldOne(): ?User {
    return User::load(1);
  }

}

/**
 * Implements a hook, so Drupal autowires the class.
 */
final class StaticEntityLoadHooks {

  /**
   * Implements hook_cron().
   */
  #[Hook('cron')]
  public function cron(): void {
    // @mago-expect analysis:drupal/static-entity-load(1)
    User::load(1)?->label();
  }

}

/**
 * Takes the entity type manager and loads through it.
 */
final class StaticEntityLoadInjected extends FormBase {

  /**
   * The user storage, documented as the entity class.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $users;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
    // @mago-expect analysis:invalid-property-assignment-value
    $this->users = $entityTypeManager->getStorage('user');
  }

  /**
   * Loads through the storage.
   */
  public function one(): mixed {
    return $this->entityTypeManager->getStorage('user')->load(1);
  }

  /**
   * A call on an object isn't a static load, whatever the docblock says.
   */
  public function documented(): ?User {
    return $this->users->load(1);
  }

}

/**
 * A plain object that nothing injects services into.
 */
final class StaticEntityLoadPlain {

  /**
   * Has no way to take the entity type manager.
   */
  public function one(): ?User {
    return User::load(1);
  }

}

/**
 * An entity loads its siblings statically, since nothing injects it.
 */
final class StaticEntityLoadEntity extends ContentEntityBase {

  /**
   * Loads another entity of its own class.
   */
  public function sibling(): ?static {
    return static::load(2);
  }

  /**
   * Loads entities of its own class.
   *
   * @return static[]
   *   The entities.
   */
  public function siblings(): array {
    return self::loadMultiple([2, 3]);
  }

}
