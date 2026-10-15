<?php

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\corpus\Nested\Greeter;
use Drupal\corpus\Nested\HomeGreeter;
use Drupal\corpus\Nested\HomeHost;
use Drupal\corpus\Nested\Other;
use Drupal\corpus\Nested\Waver;

// @mago-expect lint:drupal/doc-type-namespace(6)
/**
 * Names classes in tags other than the type tags.
 *
 * Mentions HomeHost in prose and in {@link HomeHost}, which keep its import.
 *
 * @see Other
 * @see Other::create()
 * @method Greeter greeter(Waver $waver)
 * @property Waver $waver
 * @mixin HomeGreeter
 * @phpstan-type Greeters list<Greeter>
 */
final class DocTypeNamespaceTags {

  // @mago-expect lint:drupal/doc-type-namespace(2)
  /**
   * Returns the wavers of a greeter.
   *
   * @param object $greeter
   *   The greeter.
   *
   * @return array
   *   The wavers.
   *
   * @phpstan-param Greeter $greeter
   * @psalm-return list<Waver>
   */
  public function wavers(object $greeter): array {
    return [];
  }

  /**
   * Names a function and a URL in its @see tags, which are not classes.
   *
   * @see corpus_helper()
   * @see https://www.drupal.org/project/corpus
   */
  public function references(): void {
  }

}
