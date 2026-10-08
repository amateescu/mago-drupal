<?php

/**
 * @file
 * Component tests, which run without Drupal.
 */

declare(strict_types=1);

namespace Drupal\Tests\Component\Corpus;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\TestCase;

/**
 * Extends PHPUnit's own base.
 */
final class PlainComponentTest extends TestCase {}

/**
 * Extends a core test base.
 */
// @mago-expect analysis:drupal/component-test-core-base
final class UnitComponentTest extends UnitTestCase {}

/**
 * A component test base that extends a core one.
 */
// @mago-expect analysis:drupal/component-test-core-base
abstract class KernelComponentTestBase extends KernelTestBase {}

/**
 * Its base class is the one to fix.
 */
final class KernelComponentTest extends KernelComponentTestBase {}
