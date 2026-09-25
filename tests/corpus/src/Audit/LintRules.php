<?php

/**
 * @file
 * Linter ports: discouraged functions, Yaml::parse() and render callbacks.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Component\Serialization\Yaml as DrupalYaml;
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
    fnmatch('*.yml', $yaml);
    DrupalYaml::decode($yaml);

    // @mago-expect lint:drupal/symfony-yaml-parse
    return Yaml::parse($yaml);
  }

  /**
   * Callback shapes.
   */
  public function build(callable $callback): array {
    return [
      '#pre_render' => [
        [self::class, 'preRender'],
        [$this, 'preRender'],
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
   * A trusted callback target.
   */
  public static function preRender(array $element): array {
    return $element;
  }

}
