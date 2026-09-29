<?php

/**
 * @file
 * Service classes that modules/corpus_wiring/corpus_wiring.services.yml wires.
 */

declare(strict_types=1);

namespace Drupal\corpus\Wiring;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\not_scanned\Base;

/**
 * Takes exactly two arguments.
 */
final class TwoArguments {

  /**
   * One service is one argument short.
   */
  // @mago-expect analysis:drupal/service-argument-count
  public function __construct(object $first, mixed $second) {}

}

/**
 * Takes exactly one argument.
 */
final class OneArgument {

  /**
   * One service passes one too many.
   */
  // @mago-expect analysis:drupal/service-argument-count
  public function __construct(object $first) {}

}

/**
 * The default makes the second argument optional.
 */
final class OptionalArgument {

  /**
   * One or two arguments fit; three do not.
   */
  // @mago-expect analysis:drupal/service-argument-count
  public function __construct(object $first, ?object $second = NULL) {}

}

/**
 * Takes any number of arguments after the first.
 */
final class VariadicArguments {

  /**
   * Four arguments fit.
   */
  public function __construct(object $first, object ...$rest) {}

}

/**
 * Promoted parameters count like any other.
 */
final class PromotedArguments {

  /**
   * One argument is not enough.
   */
  // @mago-expect analysis:drupal/service-argument-count
  public function __construct(
    private readonly object $first,
    private readonly object $second,
  ) {}

  /**
   * Reads both properties.
   */
  public function both(): array {
    return [$this->first, $this->second];
  }

}

/**
 * Declares the constructor a subclass inherits.
 */
abstract class ConstructorBase {

  /**
   * Takes one argument.
   */
  public function __construct(
    protected readonly object $first,
  ) {}

}

/**
 * Inherits the constructor, so the report is on the class.
 */
// @mago-expect analysis:drupal/service-argument-count
final class InheritedConstructor extends ConstructorBase {}

/**
 * Gives the classes using it a constructor.
 */
trait ConstructorTrait {

  /**
   * Takes one argument.
   */
  public function __construct(
    protected readonly object $first,
  ) {}

}

/**
 * Takes its constructor from a trait, so the report is on the class.
 */
// @mago-expect analysis:drupal/service-argument-count
final class TraitConstructor {

  use ConstructorTrait;

}

/**
 * Has no constructor, so the container passes nothing on.
 */
// @mago-expect analysis:drupal/service-argument-count
final class NoConstructor {}

/**
 * Reads the arguments past its parameters, as core does during a deprecation.
 */
final class ReadsExtraArguments {

  /**
   * Two arguments reach the constructor.
   */
  public function __construct(object $first) {
    $this->all = func_get_args();
  }

  /**
   * Every argument the container passed.
   *
   * @var array<array-key, mixed>
   */
  public array $all = [];

}

/**
 * Takes one argument more than the plain parent service passes.
 */
final class ChildOfPlainParent {

  /**
   * The child in an autowiring file inherits no autowiring.
   */
  // @mago-expect analysis:drupal/service-argument-count
  public function __construct(object $first, object $second) {}

}

/**
 * Uses the class-as-id shorthand with no arguments and no constructor.
 */
final class BareShorthand {}

/**
 * Takes one argument more than its parent service.
 */
final class ReplacedArgument {

  /**
   * The child replaces the parent's only argument.
   */
  public function __construct(object $first) {}

}

/**
 * Takes three arguments.
 */
final class ThreeArguments {

  /**
   * The parent passes one and the child one more.
   */
  // @mago-expect analysis:drupal/service-argument-count
  public function __construct(object $first, object $second, object $third) {}

}

/**
 * The stacked kernel pass prepends the wrapped kernel.
 */
final class Middleware {

  /**
   * The services file passes only the second argument.
   */
  public function __construct(object $kernel, object $first) {}

}

/**
 * The tagged handlers pass appends the collected ids.
 */
final class Collector {

  /**
   * The services file passes nothing.
   */
  public function __construct(array $ids) {}

}

/**
 * Wired by the container in ways the services file does not count.
 */
final class Unchecked {

  /**
   * Autowiring, named arguments, a factory and a compiler pass decide.
   */
  public function __construct(object $first, object $second) {}

}

/**
 * Only a factory can build it.
 */
final class PrivateConstructor {

  /**
   * The container cannot call it, which is not a count problem.
   */
  private function __construct(object $first) {}

}

/**
 * Its parent is not in the codebase, so its constructor is unknown.
 */
// @mago-expect analysis:non-existent-class-like
final class UnscannedParent extends Base {}

/**
 * Its service definition sets the cache and the alter hook.
 */
final class CalledManager extends DefaultPluginManager {

  /**
   * Leaves the wiring to the service definition.
   */
  public function __construct(object $cache) {}

}

/**
 * Its service inherits the cache call from an abstract parent.
 */
final class InheritingManager extends DefaultPluginManager {

  /**
   * Sets the alter hook itself.
   */
  public function __construct(object $cache) {
    $this->alterInfo('corpus_inheriting');
  }

}

/**
 * Its service definition sets only the cache.
 */
final class HalfCalledManager extends DefaultPluginManager {

  /**
   * Nothing sets the alter hook.
   */
  // @mago-expect analysis:drupal/plugin-manager-alter-info
  public function __construct(object $cache) {}

}
