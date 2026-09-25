<?php

/**
 * @file
 * Config lookups typed through modules/corpus/config/schema/corpus.schema.yml.
 *
 * A typed `get()` satisfies the declared return type on its own; an untyped one
 * returns `mixed` and is reported.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\StorageInterface;

/**
 * Reads the corpus settings through every entry point that gets tagged.
 */
final class Configuration {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
  ) {}

  /**
   * Scalar keys map to their PHP types, with null for an absent key.
   */
  public function name(): string {
    return $this->configFactory->get('corpus.settings')->get('name') ?? '';
  }

  /**
   * The static helper tags the same way.
   */
  public function count(): int {
    return \Drupal::config('corpus.settings')->get('count') ?? 0;
  }

  /**
   * Editable configs carry the tag too.
   */
  public function enabled(): bool {
    return $this->configFactory->getEditable('corpus.settings')->get('enabled') ?? FALSE;
  }

  /**
   * A float key.
   */
  public function ratio(): float {
    return \Drupal::configFactory()->get('corpus.settings')->get('ratio') ?? 0.0;
  }

  /**
   * A sequence becomes an array of its element type.
   *
   * @return array<int|string, string>
   */
  public function tags(): array {
    return \Drupal::config('corpus.settings')->get('tags') ?? [];
  }

  /**
   * Nested mapping keys resolve by dotted path.
   */
  public function front(): string {
    return \Drupal::config('corpus.settings')->get('page.front') ?? '';
  }

  /**
   * A mapping key is a shape of its own keys, with null for an absent key.
   */
  public function frontThroughPage(): string {
    return \Drupal::config('corpus.settings')->get('page')['front'] ?? '';
  }

  /**
   * Stored data has the shape of the whole object, or is FALSE.
   */
  public function stored(StorageInterface $storage): string {
    $data = $storage->read('corpus.settings');

    return $data === FALSE ? '' : $data['page']['front'];
  }

  /**
   * Without `FullyValidatable`, stored data stays an array of anything.
   */
  public function storedLoose(StorageInterface $storage): string {
    $data = $storage->read('corpus.loose');

    // @mago-expect analysis:mixed-return-statement
    return is_array($data) ? $data['name'] : '';
  }

  /**
   * A wildcard schema name covers the config entities under it.
   */
  public function weight(): int {
    return \Drupal::config('corpus.item.first')->get('weight') ?? 0;
  }

  /**
   * The tag survives a variable.
   */
  public function viaVariable(): int {
    $config = \Drupal::config('corpus.settings');

    return $config->get('count') ?? 0;
  }

  /**
   * Without `FullyValidatable`, the schema promises nothing.
   */
  public function loose(): string {
    // @mago-expect analysis:mixed-return-statement
    return \Drupal::config('corpus.loose')->get('name');
  }

  /**
   * A dynamic type reference resolves only at runtime.
   */
  public function dynamic(): string {
    // @mago-expect analysis:mixed-return-statement
    return \Drupal::config('corpus.settings')->get('dynamic');
  }

  /**
   * A computed name or key keeps the declared `mixed`.
   */
  public function computed(string $name, string $key): string {
    // @mago-expect analysis:mixed-return-statement
    return \Drupal::config($name)->get('name');
  }

  /**
   * Keys the fully validatable schema does not list are reported.
   */
  public function unknownKeys(string $key): void {
    // @mago-expect analysis:drupal/config-unknown-key
    \Drupal::config('corpus.settings')->get('nope');
    // @mago-expect analysis:drupal/config-unknown-key
    \Drupal::config('corpus.settings')->get('page.back');
    // Not fully validatable, so an unknown key is not a mistake the
    // schema can prove.
    \Drupal::config('corpus.loose')->get('nope');
    \Drupal::config('corpus.settings')->get($key);
    \Drupal::config('corpus.settings')->get('dynamic.anything');
  }

  /**
   * A name no schema describes is reported when its module is known.
   */
  public function unknownNames(): void {
    // @mago-expect analysis:drupal/config-unknown-name
    \Drupal::config('corpus.setings');
    // A wildcard schema covers the name.
    \Drupal::config('corpus.item.second');
    // The module is not in the codebase, so its schema cannot be checked.
    \Drupal::config('absent_module.settings');
  }

}
