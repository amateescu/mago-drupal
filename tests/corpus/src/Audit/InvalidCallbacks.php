<?php

/**
 * @file
 * Render callback values whose type PHP can never call.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Security\Attribute\TrustedCallback;

/**
 * Builds elements with computed callbacks.
 */
final class ComputedCallbacks {

  /**
   * Scalars and NULL under the render, date and component keys.
   */
  public function scalars(array $element, ?int $maybe): array {
    $element['number'] = [
      // @mago-expect analysis:drupal/invalid-callback
      '#pre_render' => [123],
    ];
    $element['null'] = [
      // @mago-expect analysis:drupal/invalid-callback
      '#lazy_builder' => [NULL, []],
    ];
    $element['boolean'] = [
      // @mago-expect analysis:drupal/invalid-callback
      '#access_callback' => FALSE,
    ];
    $element['date'] = [
      // @mago-expect analysis:drupal/invalid-callback
      '#date_time_callbacks' => [$maybe],
    ];
    $element['component'] = [
      // @mago-expect analysis:drupal/invalid-callback
      '#propsAlter' => [4.2],
    ];
    // @mago-expect analysis:drupal/invalid-callback
    $element['#post_render'] = [TRUE];

    return $element;
  }

  /**
   * Arrays that can't be pairs, and an object without __invoke().
   */
  public function shapes(array $element): array {
    $element['three'] = [
      // @mago-expect analysis:drupal/invalid-callback
      '#pre_render' => [[static::class, 'preRender', 'extra']],
    ];
    $element['empty'] = [
      // @mago-expect analysis:drupal/invalid-callback
      '#pre_render' => [[]],
    ];
    $element['object'] = [
      // @mago-expect analysis:drupal/invalid-callback
      '#pre_render' => [$this],
    ];

    return $element;
  }

  /**
   * Values that may be callable, by their type.
   */
  public function fine(
    array $element,
    string $name,
    ?string $maybe,
    callable $callable,
    Invoked $invoked,
    mixed $anything,
  ): array {
    $closure = static fn (array $element): array => $element;
    $pair = $this->pair();
    $element['fine'] = [
      '#pre_render' => [
        $name,
        $maybe,
        $callable,
        $pair,
        $invoked,
        $anything,
        $closure,
        [$this, $name],
        [static::class, 'preRender'],
      ],
      '#lazy_builder' => [$name, []],
      '#access_callback' => $callable,
    ];
    // Not a callback key, so not read.
    $element['#markup'] = 123;

    return $element;
  }

  /**
   * A pair read from somewhere else, typed as any array of strings.
   *
   * @return array<int, string>
   *   The pair.
   */
  private function pair(): array {
    return [static::class, 'preRender'];
  }

  /**
   * Trusted for the fine cases.
   */
  #[TrustedCallback]
  public static function preRender(array $element): array {
    return $element;
  }

}

/**
 * Has __invoke(), so an object of it can be called.
 */
final class Invoked {

  /**
   * Runs the callback.
   */
  public function __invoke(array $element): array {
    return $element;
  }

}
