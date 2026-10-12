<?php

declare(strict_types=1);

namespace Drupal\Tests\corpus\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests a page, but PHPUnit never runs it.
 */
// @mago-expect lint:drupal/test-class-suffix
final class RemovedTags extends BrowserTestBase {

  /**
   * Checks the page.
   */
  public function testPage(): void {
  }

}
