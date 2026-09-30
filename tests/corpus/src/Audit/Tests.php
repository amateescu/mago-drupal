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
use Symfony\Component\DependencyInjection\ContainerInterface;

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

  /**
   * PHPUnit counts a Countable, so an empty one passes `assertEmpty()`.
   */
  public function emptyCountable(\ArrayObject $items): int {
    $this->assertEmpty($items);
    return $items->count();
  }

  /**
   * A plain object may be Countable too.
   */
  public function emptyObject(object $items): object {
    $this->assertEmpty($items);
    return $items;
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

/**
 * Sets the theme for the tests using it.
 */
trait ThemeTrait {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

}

/**
 * Takes its theme from a trait.
 */
final class TraitThemeTest extends BrowserTestBase {

  use ThemeTrait;

}

/**
 * Hands its trait's theme down.
 */
abstract class TraitThemeTestBase extends BrowserTestBase {

  use ThemeTrait;

}

/**
 * Takes its theme from the trait of its base class.
 */
final class InheritedTraitThemeTest extends TraitThemeTestBase {}

/**
 * Installs a profile that ships a theme.
 */
trait StandardProfileTrait {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'standard';

}

/**
 * Takes a themed profile from a trait.
 */
final class TraitProfileTest extends BrowserTestBase {

  use StandardProfileTrait;

}

/**
 * Installs a themeless profile and sets no theme.
 */
trait TestingProfileTrait {

  /**
   * {@inheritdoc}
   */
  protected $profile = 'testing';

}

/**
 * Its trait names a profile, but no theme.
 */
// @mago-expect analysis:drupal/browser-test-default-theme
final class TraitThemelessProfileTest extends BrowserTestBase {

  use TestingProfileTrait;

}

/**
 * Sets the theme when the test installs it.
 */
trait RuntimeThemeTrait {

  /**
   * {@inheritdoc}
   */
  protected function installDefaultThemeFromClassProperty(ContainerInterface $container) {
    $this->defaultTheme ??= 'stark';
  }

}

/**
 * Takes its theme from a trait at run time.
 */
final class RuntimeThemeTest extends BrowserTestBase {

  use RuntimeThemeTrait;

}

/**
 * Sets the theme in setUp(), before the parent installs it.
 */
final class SetUpThemeTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    $this->defaultTheme = 'stark';
    parent::setUp();
  }

}

/**
 * Sets the theme from a constant.
 */
final class ConstantThemeTest extends BrowserTestBase {

  private const THEME = 'stark';

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = self::THEME;

}

/**
 * Names its profile with a constant, which the check cannot read.
 */
final class ConstantProfileTest extends BrowserTestBase {

  private const PROFILE = 'testing';

  /**
   * {@inheritdoc}
   */
  protected $profile = self::PROFILE;

}
