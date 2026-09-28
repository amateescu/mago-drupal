<?php

/**
 * @file
 * Deprecated interfaces, constants, properties and types Mago does not check.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\corpus_dep\ApiKeepingMethod;
use Drupal\corpus_dep\ApiWithDeprecatedMethod;
use Drupal\corpus_dep\ApiWithDeprecatedMethodInterface;
use Drupal\corpus_dep\ApiWithLegacyMembers;
use Drupal\corpus_dep\LaterApiInterface;
use Drupal\corpus_dep\LegacyApiInterface;
use Drupal\corpus_dep\LegacyException;
use Drupal\corpus_dep\LegacyFactory;

/**
 * Implements an interface Drupal 12 removes.
 */
// @mago-expect analysis:drupal/deprecated-class
final class LegacyImplementer implements LegacyApiInterface {}

/**
 * Implements an interface Drupal 13 removes, which the target leaves out.
 */
final class LaterImplementer implements LaterApiInterface {}

/**
 * Reads the deprecated members of its parent, one case per method.
 */
final class LegacyMemberUser extends ApiWithLegacyMembers {

  /**
   * A deprecated constant, named by its class.
   */
  public function replaced(): int {
    // @mago-expect analysis:drupal/deprecated-class-constant
    return ApiWithLegacyMembers::EXISTS_REPLACE;
  }

  /**
   * A deprecated constant a parent declares.
   */
  public function inherited(): int {
    // @mago-expect analysis:drupal/deprecated-class-constant
    return static::EXISTS_REPLACE;
  }

  /**
   * A constant that is not deprecated.
   */
  public function current(): int {
    return ApiWithLegacyMembers::CURRENT;
  }

  /**
   * A constant of a deprecated interface.
   */
  public function level(): int {
    // @mago-expect analysis:drupal/deprecated-class
    return LegacyApiInterface::LEVEL;
  }

  /**
   * A deprecated property a parent declares.
   */
  public function cache(): array {
    // @mago-expect analysis:drupal/deprecated-property
    return $this->legacyCache;
  }

  /**
   * A deprecated static property.
   */
  public function total(): int {
    // @mago-expect analysis:drupal/deprecated-property
    return ApiWithLegacyMembers::$legacyCount;
  }

  /**
   * A static call on a deprecated class.
   */
  public function make(): object {
    // @mago-expect analysis:drupal/deprecated-class
    return LegacyFactory::make();
  }

  /**
   * A parameter type and a caught exception.
   */
  // @mago-expect analysis:drupal/deprecated-class
  public function typed(?LegacyApiInterface $api): ?object {
    try {
      return $api;
    }
    // @mago-expect analysis:drupal/deprecated-class
    catch (LegacyException) {
      return NULL;
    }
  }

  /**
   * Deprecated code may use other deprecated code.
   *
   * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is
   *   no replacement.
   *
   * @see https://www.drupal.org/node/1234567
   */
  public function legacy(): int {
    return self::EXISTS_REPLACE + count($this->legacyCache);
  }

}

/**
 * Calls a method whose interface declaration is deprecated.
 */
final class DeprecatedMethodCalls {

  /**
   * The implementation only says `{@inheritdoc}`.
   */
  public function implementation(ApiWithDeprecatedMethod $api): object {
    // @mago-expect analysis:drupal/deprecated-method
    return $api->trust();
  }

  /**
   * On the interface, Mago reports it itself.
   */
  public function declaration(ApiWithDeprecatedMethodInterface $api): object {
    // @mago-expect analysis:deprecated-method
    return $api->trust();
  }

  /**
   * An override marked `@not-deprecated` is fine.
   */
  public function kept(ApiKeepingMethod $api): object {
    return $api->trust();
  }

}
