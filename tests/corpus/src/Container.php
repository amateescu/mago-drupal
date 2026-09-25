<?php

/**
 * @file
 * Container lookups typed through modules/corpus/corpus.services.yml.
 *
 * A method that does not exist on the resolved class proves the provider
 * replaced the declared `?object`; an unresolved id keeps the declared type
 * and reports nothing.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\corpus\Nested\Other;
use Drupal\corpus\Nested\Thing;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Exercises every service shape the index resolves.
 */
final class Container {

  public function __construct(
    private readonly ContainerInterface $container,
  ) {}

  /**
   * Resolves through a plain `class:` definition.
   */
  public function plain(): void {
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.thing')->missing();
  }

  /**
   * Resolves through the static helper.
   */
  public function helper(): void {
    // @mago-expect analysis:non-existent-method
    \Drupal::service('corpus.thing')->missing();
  }

  /**
   * Resolves the `'@id'` string shorthand.
   */
  public function shorthandAlias(): void {
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.alias')->missing();
  }

  /**
   * Resolves an `alias:` key, whose definition also carries a deprecation.
   */
  public function keyedAlias(): void {
    // @mago-expect analysis:non-existent-method
    // @mago-expect analysis:drupal/deprecated-service
    $this->container->get('corpus.aliased')->missing();
    // @mago-expect analysis:drupal/deprecated-service
    \Drupal::service('corpus.aliased');
    // @mago-expect analysis:drupal/deprecated-service
    \Drupal::classResolver('corpus.aliased');
    // Probing never instantiates, so it is not a deprecated use.
    $this->container->has('corpus.aliased');
  }

  /**
   * A decorated id returns the decorator; the original moves to `.inner`.
   */
  public function decorated(): void {
    $this->container->get('corpus.decorated')->onlyOnOther();
    // An alias of the decorated id ends at the decorator too.
    $this->container->get('corpus.decorated_alias')->onlyOnOther();
    // The container keeps the moved definition private.
    // @mago-expect analysis:drupal/unknown-service
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.decorator.inner')->onlyOnOther();
    // A decorator from a module this run does not analyze leaves the
    // class the id had, which beats reporting a bare object.
    $this->container->get('corpus.outside_decorated')->onlyOnThing();
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.outside_decorated')->onlyOnOther();
    // The container drops a decorator whose target is missing, so the id
    // is unknown at runtime too.
    // @mago-expect analysis:drupal/unknown-service
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.ignored_decorator')->onlyOnOther();
  }

  /**
   * The class resolver returns the class it is asked for.
   */
  public function classResolver(ClassResolverInterface $resolver): void {
    // @mago-expect analysis:non-existent-method
    \Drupal::classResolver(Thing::class)->missing();
    // @mago-expect analysis:non-existent-method
    $resolver->getInstanceFromDefinition(Thing::class)->missing();
    // @mago-expect analysis:non-existent-method
    $resolver->getInstanceFromDefinition('corpus.thing')->missing();
    // @mago-expect analysis:ambiguous-object-method-access
    $resolver->getInstanceFromDefinition('corpus.not_defined')->missing();
  }

  /**
   * Inherits the class from an abstract parent.
   */
  public function parent(): void {
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.child')->missing();
  }

  /**
   * Uses the class-as-id shorthand.
   */
  public function classId(): void {
    // @mago-expect analysis:non-existent-method
    $this->container->get(Thing::class)->missing();
  }

  /**
   * Resolves registrations made in PHP by CorpusServiceProvider.
   */
  public function provided(): void {
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.provided')->missing();
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.provided_string')->missing();
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.chained')->missing();
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.defined')->missing();
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.provided_alias')->missing();
    // A literal id with a computed class is a known service of unknown
    // type, so nothing is reported.
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.provided_dynamic')->missing();
  }

  /**
   * A null-on-invalid lookup keeps null in the type.
   */
  public function nullable(): ?Thing {
    return $this->container->get('corpus.thing', ContainerInterface::NULL_ON_INVALID_REFERENCE);
  }

  /**
   * Ignore-on-invalid also returns null.
   */
  public function ignorable(): ?Thing {
    return $this->container->get('corpus.thing', ContainerInterface::IGNORE_ON_INVALID_REFERENCE);
  }

  /**
   * Behaviors other than exception-on-invalid return null when missing.
   *
   * So the type keeps null, and an unknown id is not reported.
   */
  public function otherBehaviors(): void {
    // @mago-expect analysis:possible-method-access-on-null
    $this->container->get('corpus.thing', ContainerInterface::RUNTIME_EXCEPTION_ON_INVALID_REFERENCE)->onlyOnThing();
    // @mago-expect analysis:possible-method-access-on-null
    $this->container->get('corpus.thing', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)->onlyOnThing();
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.not_defined', ContainerInterface::IGNORE_ON_UNINITIALIZED_REFERENCE)->missing();
  }

  /**
   * The container leaves a private service out, but not an alias of it.
   */
  public function privateService(): void {
    // @mago-expect analysis:drupal/unknown-service
    $this->container->get('corpus.private')->onlyOnThing();
    $this->container->get('corpus.private_alias')->onlyOnThing();
  }

  /**
   * A computed behavior keeps the declared `?object`.
   */
  public function computedBehavior(int $behavior): void {
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.thing', $behavior)->missing();
  }

  /**
   * Ids the index cannot type keep the declared `?object`.
   */
  public function unresolved(): void {
    // @mago-expect analysis:drupal/unknown-service
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.not_defined')->missing();
    // @mago-expect analysis:drupal/unknown-service
    \Drupal::service('corpus.not_defined');
    // An abstract definition never becomes a service.
    // @mago-expect analysis:drupal/unknown-service
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.base')->missing();
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.factory_only')->missing();
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.placeholder')->missing();
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.unknown_class')->missing();
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get($this->dynamicId())->missing();
  }

  /**
   * Unknown ids that are not reported: a null probe and a class name.
   *
   * The container registers hook classes and other autowired services under
   * their class name without a YAML line.
   */
  public function unknownButAllowed(): ?object {
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get(Other::class)->missing();

    return $this->container->get('corpus.not_defined', ContainerInterface::NULL_ON_INVALID_REFERENCE);
  }

  /**
   * Stands in for an id chosen at runtime.
   */
  private function dynamicId(): string {
    return 'corpus.thing';
  }

}
