<?php

/**
 * @file
 * Test class conventions.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\corpus\Nested\Thing;
use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\corpus\LooseTestBase;
use PHPUnit\Framework\TestCase;

/**
 * Named like a test, wired like one.
 */
final class WellFormedTest extends TestCase {

  /**
   * Modules to install.
   *
   * @var list<string>
   */
  protected static $modules = ['corpus'];

  /**
   * Reads the list so it is not unused.
   */
  public function modules(): array {
    return self::$modules;
  }

  /**
   * PHPUnit annotates `assertNotNull()` but not the emptiness assertions.
   */
  public function emptiness(?Thing $present, ?Thing $absent): void {
    $this->assertNotEmpty($present);
    $present->onlyOnThing();

    $this->assertEmpty($absent);
    // @mago-expect analysis:method-access-on-null
    $absent->onlyOnThing();
  }

}

/**
 * Misses the suffix and exposes the modules list.
 */
// @mago-expect analysis:drupal/test-class-suffix
final class BadlyNamed extends TestCase {

  /**
   * Modules to install, wrongly public.
   *
   * @var list<string>
   */
  // @mago-expect analysis:drupal/test-modules-visibility
  public static $modules = ['corpus'];

}

/**
 * Abstract bases may carry any name.
 */
abstract class CorpusTestBase extends TestCase {}

/**
 * Inherits a public $modules; the base class is the one to fix.
 */
final class LooseChildTest extends LooseTestBase {}

/**
 * A functional test with a theme.
 */
final class ThemedTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}

/**
 * A functional test without a theme.
 */
// @mago-expect analysis:drupal/browser-test-default-theme
final class ThemelessTest extends BrowserTestBase {}

/**
 * A profile with a theme of its own needs none.
 */
final class StandardProfileTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

}

/**
 * Update path tests install from a database dump and choose no theme.
 */
final class SomeUpdateTest extends UpdatePathTestBase {}

/**
 * A base class that sets the theme for the tests below it.
 */
abstract class ThemedTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}

/**
 * Inherits its theme from the base class.
 */
final class InheritedThemeTest extends ThemedTestBase {}

/**
 * A base class that leaves the theme to the tests below it.
 */
abstract class UnthemedTestBase extends BrowserTestBase {}

/**
 * Neither it nor its base class sets a theme.
 */
// @mago-expect analysis:drupal/browser-test-default-theme
final class IndirectlyThemelessTest extends UnthemedTestBase {}

/**
 * Installs from existing configuration, which picks the theme itself.
 */
abstract class ExistingConfigTestBase extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $profile = NULL;

}

/**
 * Takes the theme from the configuration it installs.
 */
final class ExistingConfigTest extends ExistingConfigTestBase {}
