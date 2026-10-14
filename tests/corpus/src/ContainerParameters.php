<?php

declare(strict_types=1);

// Container parameters typed from the `parameters:` sections.
// A value passed where its kind cannot go is an invalid argument once the
// provider types it; under the declared union it is only a possibly invalid
// one.
namespace Drupal\corpus;

use Drupal\Core\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Reads parameters of every kind the services files define.
 */
final class ContainerParameters {

  public function __construct(
    private readonly ContainerInterface $container,
  ) {}

  /**
   * A string parameter, empty in YAML, is still a string.
   */
  public function root(): string {
    return $this->container->getParameter('app.root');
  }

  /**
   * A bool parameter.
   */
  public function flag(): void {
    // @mago-expect analysis:invalid-argument
    $this->takesString($this->container->getParameter('corpus.flag'));
  }

  /**
   * An int parameter.
   */
  public function limit(): void {
    // @mago-expect analysis:invalid-argument
    $this->takesString($this->container->getParameter('corpus.limit'));
  }

  /**
   * A float parameter.
   */
  public function ratio(): float {
    return $this->container->getParameter('corpus.ratio');
  }

  /**
   * A mapping and a list are both arrays, with no shape.
   */
  public function options(): void {
    // @mago-expect analysis:invalid-argument
    $this->takesString($this->container->getParameter('corpus.options'));
  }

  /**
   * A list parameter.
   */
  public function list(): array {
    return $this->container->getParameter('corpus.list');
  }

  /**
   * A string with a placeholder inside stays a string.
   */
  public function path(): string {
    return $this->container->getParameter('corpus.path');
  }

  /**
   * An escaped `@` stays a string.
   */
  public function escaped(): string {
    return $this->container->getParameter('corpus.escaped');
  }

  /**
   * Two files give the parameter two kinds, so it is either.
   */
  public function mixed(): void {
    // @mago-expect analysis:possibly-invalid-argument
    $this->takesInt($this->container->getParameter('corpus.mixed'));
  }

  /**
   * The global container reads through the same interface.
   */
  public function global(): void {
    // @mago-expect analysis:invalid-argument
    $this->takesString(\Drupal::getContainer()->getParameter('corpus.flag'));
  }

  /**
   * Values the container resolves at runtime keep the declared type.
   *
   * That covers a null, a whole `%name%` reference, a service reference, a
   * custom tag, a name no file defines and a computed name.
   */
  public function unresolved(string $name): void {
    // @mago-expect analysis:possibly-invalid-argument
    // @mago-expect analysis:possibly-null-argument
    $this->takesString($this->container->getParameter('password.algorithm'));
    // @mago-expect analysis:possibly-invalid-argument
    // @mago-expect analysis:possibly-null-argument
    $this->takesString($this->container->getParameter('corpus.copy'));
    // @mago-expect analysis:possibly-invalid-argument
    // @mago-expect analysis:possibly-null-argument
    $this->takesString($this->container->getParameter('corpus.reference'));
    // @mago-expect analysis:possibly-invalid-argument
    // @mago-expect analysis:possibly-null-argument
    $this->takesString($this->container->getParameter('corpus.tagged'));
    // @mago-expect analysis:possibly-invalid-argument
    // @mago-expect analysis:possibly-null-argument
    $this->takesString($this->container->getParameter('corpus.not_defined'));
    // @mago-expect analysis:possibly-invalid-argument
    // @mago-expect analysis:possibly-null-argument
    $this->takesString($this->container->getParameter($name));
  }

  /**
   * A container class, as service providers and compiler passes hold.
   */
  public function classReceiver(Container $container): void {
    // @mago-expect analysis:invalid-argument
    $this->takesString($container->getParameter('corpus.flag'));
  }

  /**
   * Stands in for a caller that needs a string.
   */
  private function takesString(string $value): void {
  }

  /**
   * Stands in for a caller that needs an int.
   */
  private function takesInt(int $value): void {
  }

}
