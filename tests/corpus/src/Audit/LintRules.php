<?php

// @mago-expect lint:drupal/file-comment
/**
 * @file
 * Linter ports: discouraged functions, Yaml::parse() and render callbacks.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Component\Serialization\Yaml as DrupalYaml;
use Drupal\Core\Security\Attribute\TrustedCallback;
use Symfony\Component\Yaml\Yaml;

/**
 * Builds render arrays with good and bad callbacks.
 */
final class LintRules {

  /**
   * Debug helpers and the Symfony parser.
   */
  public function helpers(string $yaml): mixed {
    // @mago-expect lint:drupal/discouraged-function
    dpm($yaml);
    // @mago-expect lint:drupal/discouraged-function
    ksm($yaml);
    // @mago-expect lint:drupal/discouraged-function
    fnmatch('*.yml', $yaml);
    DrupalYaml::decode($yaml);

    // @mago-expect lint:drupal/symfony-yaml-parse
    return Yaml::parse($yaml);
  }

  /**
   * Debug helpers as first-class callables.
   */
  public function callables(): array {
    return [
      // @mago-expect lint:drupal/discouraged-function
      dpm(...),
      // @mago-expect lint:drupal/discouraged-function
      \fnmatch(...),
      strlen(...),
    ];
  }

  /**
   * Callback shapes.
   */
  public function build(callable $callback): array {
    return [
      '#pre_render' => [
        [self::class, 'preRender'],
        [$this, 'preRender'],
        // Only the form API turns "::method" into a method of the form
        // object.
        // @mago-expect analysis:drupal/unknown-callback
        '::preRender',
        $callback,
        'corpus.thing:render',
        static fn (array $element): array => $element,
        // @mago-expect lint:drupal/render-callback
        'corpus_pre_render',
      ],
      // @mago-expect lint:drupal/render-callback
      '#post_render' => 'corpus_post_render',
      '#lazy_builder' => ['corpus.thing:build', []],
      // @mago-expect lint:drupal/render-callback
      '#access_callback' => 'corpus_access',
      '#cache' => ['max-age' => 0],
    ];
  }

  /**
   * Component callbacks, which core passes through doTrustedCallback().
   */
  public function component(): array {
    return [
      '#type' => 'component',
      '#propsAlter' => [
        [self::class, 'preRender'],
        // @mago-expect lint:drupal/render-callback
        'corpus_props_alter',
      ],
      // @mago-expect lint:drupal/render-callback
      '#slotsAlter' => ['corpus_slots_alter'],
    ];
  }

  /**
   * A trusted callback target.
   */
  #[TrustedCallback]
  public static function preRender(array $element): array {
    return $element;
  }

}
