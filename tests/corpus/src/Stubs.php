<?php

declare(strict_types=1);

// Signatures the shipped stub files sharpen.
namespace Drupal\corpus;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\StringTranslation\PluralTranslatableMarkup;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

use function t;

/**
 * Exercises the stub files loaded by the initialization hook.
 */
final class Stubs {

  use StringTranslationTrait;

  /**
   * A stubbed interface keeps its constants and methods, restated.
   */
  public function restated(CacheBackendInterface $cache): void {
    $this->wantsInt(CacheBackendInterface::CACHE_PERMANENT);
    $cache->deleteAll();
  }

  /**
   * A cache item is a shape, not a bare object.
   *
   * The database backend returns the timestamps as strings and adds
   * properties of its own.
   */
  public function cacheItem(CacheBackendInterface $cache): void {
    $item = $cache->get('corpus');
    if ($item === FALSE) {
      return;
    }

    $this->wantsString($item->cid);
    // The database backend returns `expire` as a numeric string.
    // @mago-expect analysis:possibly-invalid-argument
    $this->wantsInt($item->expire);
    // A backend's own property can be read, but the shape does not type it.
    // @mago-expect analysis:ambiguous-object-property-access
    $this->wantsMixed($item->checksum);
  }

  /**
   * A decorator passes plain arrays on, the way core documents them.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   The cache backend.
   * @param array $cids
   *   The cache IDs.
   * @param array $items
   *   The cache items.
   */
  public function passThrough(CacheBackendInterface $cache, array $cids, array $items): void {
    $cache->setMultiple($items);
    $cache->deleteMultiple($cids);
    foreach ($cache->getMultiple($cids) as $cid => $item) {
      // A numeric cache ID comes back as an integer key.
      // @mago-expect analysis:less-specific-argument
      $this->wantsString($cid);
    }
    // @mago-expect analysis:deprecated-method
    $cache->invalidateAll();
  }

  /**
   * A placeholder value goes through Html::escape(), which takes a string.
   *
   * A scalar, a Stringable object and markup are cast. NULL, an array or any
   * other object throws a TypeError.
   */
  public function placeholders(TranslationInterface $translation, ?string $maybe, \Stringable $label): void {
    $this->t('@a %b :c', ['@a' => 1, '%b' => $label, ':c' => $this->t('Nested')]);
    // The procedural t() takes the same arguments.
    // @mago-expect lint:drupal/global-function
    // @mago-expect analysis:possibly-invalid-argument
    t('@a', ['@a' => NULL]);
    // @mago-expect analysis:possibly-invalid-argument
    $this->t('@a', ['@a' => $maybe]);
    // @mago-expect analysis:possibly-invalid-argument
    $this->formatPlural(2, 'One', '@count @a', ['@a' => []]);
    // @mago-expect analysis:possibly-invalid-argument
    new FormattableMarkup('@a', ['@a' => new \stdClass()]);
    // @mago-expect analysis:possibly-invalid-argument
    $translation->translate('@a', ['@a' => NULL]);
  }

  /**
   * A class below a stubbed class inherits only what the stub declares.
   */
  public function inheritedMarkup(PluralTranslatableMarkup $plural): void {
    $this->wantsString($plural->getUntranslatedString());
    $this->wantsString($plural->jsonSerialize());
    $this->wantsString($this->getStringTranslation()->translate('Hello')->render());
  }

  /**
   * Takes anything, for a property the shape leaves open.
   */
  private function wantsMixed(mixed $value): void {
  }

  /**
   * Takes a string, so a wrong type shows up as an argument error.
   */
  private function wantsString(string $value): void {
  }

  /**
   * Takes an int, so a wrong type shows up as an argument error.
   */
  private function wantsInt(int $value): void {
  }

}
