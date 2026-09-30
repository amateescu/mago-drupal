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
    // Drupal 13 removes this one, so --deprecations=12 skips it.
    \Drupal::service('corpus.retiring');
    \Drupal::service('corpus.retiring_alias');
    // An alias raises the deprecation of the service it points at.
    // @mago-expect analysis:drupal/deprecated-service
    \Drupal::service('corpus.leaving_alias');
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
    // Another module's decorator is there only while that module is on, so
    // the id has the type both classes share.
    $this->container->get('corpus.greeter')->greet();
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.greeter')->shout();
    // Two interfaces neither extends are both shared, so the id is both.
    $this->container->get('corpus.host')->greet();
    $this->container->get('corpus.host')->wave();
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.host')->onlyAtHome();
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
    $this->container->get('corpus.provided_alias')->missing();
    // A literal id with a computed class is a known service of unknown
    // type, so nothing is reported.
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.provided_dynamic')->missing();
  }

  /**
   * A definition a provider hands to setDefinition() is private.
   */
  public function providedPrivate(): void {
    // @mago-expect analysis:drupal/unknown-service
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.defined')->missing();
  }

  /**
   * Unless setPublic(TRUE) runs on the chain that builds it.
   */
  public function providedPublicChain(): void {
    $this->container->get('corpus.defined_public')->onlyOnThing();
  }

  /**
   * Or on the definition setDefinition() returns.
   */
  public function providedPublicResult(): void {
    $this->container->get('corpus.defined_result')->onlyOnThing();
  }

  /**
   * Or on the variable that holds it.
   */
  public function providedPublicVariable(): void {
    $this->container->get('corpus.defined_variable')->onlyOnThing();
  }

  /**
   * A variable nothing makes public holds a private definition.
   */
  public function providedPrivateVariable(): void {
    // @mago-expect analysis:drupal/unknown-service
    $this->container->get('corpus.defined_private_variable')->onlyOnThing();
  }

  /**
   * A literal setPublic(FALSE) makes a registered service private.
   */
  public function registeredPrivate(): void {
    // @mago-expect analysis:drupal/unknown-service
    $this->container->get('corpus.registered_private')->onlyOnThing();
  }

  /**
   * A child definition takes its parent's class and visibility.
   */
  public function providedChild(): void {
    // @mago-expect analysis:non-existent-method
    $this->container->get('corpus.defined_child')->missing();
  }

  /**
   * A definition built in another method may be public.
   */
  public function providedElsewhere(): void {
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.defined_elsewhere')->missing();
  }

  /**
   * So may one another method finishes.
   */
  public function providedConfigured(): void {
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.defined_configured')->missing();
  }

  /**
   * A variable reused for two definitions makes both public, untyped.
   */
  public function providedReused(): void {
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.reused_first')->missing();
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:ambiguous-object-method-access
    $this->container->get('corpus.reused_second')->missing();
  }

  /**
   * Public once code the scan does not follow gets the definition.
   */
  public function providedPublicLater(): void {
    $this->container->get('corpus.defined_assigned')->onlyOnThing();
    $this->container->get('corpus.defined_altered')->onlyOnThing();
    $this->container->get('corpus.computed_visibility')->onlyOnThing();
  }

  /**
   * A literal setPublic(FALSE) makes an alias private.
   */
  public function privateAlias(): void {
    // @mago-expect analysis:drupal/unknown-service
    $this->container->get('corpus.provided_private_alias')->onlyOnThing();
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
   * The container leaves a private service out, but not a plain alias of it.
   *
   * An alias that says it is private is left out too.
   */
  public function privateService(): void {
    // @mago-expect analysis:drupal/unknown-service
    $this->container->get('corpus.private')->onlyOnThing();
    $this->container->get('corpus.private_alias')->onlyOnThing();
    // @mago-expect analysis:drupal/unknown-service
    $this->container->get('corpus.closed_alias')->onlyOnThing();
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
   * their class name without a YAML line, so a class name id gets that class,
   * and `null` too when the behavior asks for it.
   */
  public function unknownButAllowed(): ?object {
    // @mago-expect analysis:non-existent-method
    $this->container->get(Other::class)->missing();
    // @mago-expect analysis:non-existent-method
    \Drupal::service(Other::class)->missing();
    // @mago-expect analysis:possible-method-access-on-null
    // @mago-expect analysis:non-existent-method
    $this->container->get(Other::class, ContainerInterface::NULL_ON_INVALID_REFERENCE)->missing();

    return $this->container->get('corpus.not_defined', ContainerInterface::NULL_ON_INVALID_REFERENCE);
  }

  /**
   * Stands in for an id chosen at runtime.
   */
  private function dynamicId(): string {
    return 'corpus.thing';
  }

}
