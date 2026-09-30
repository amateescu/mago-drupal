<?php

/**
 * @file
 * Symbols another module deprecates, with removal in Drupal 12 and 13.
 */

declare(strict_types=1);

namespace Drupal\corpus_dep;

/**
 * An interface Drupal 12 removes.
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
 *   \Drupal\corpus_dep\CurrentApi instead.
 */
interface LegacyApiInterface
{
    const LEVEL = 1;
}

/**
 * An interface Drupal 13 removes.
 *
 * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. There is
 *   no replacement.
 */
interface LaterApiInterface {}

/**
 * A class with deprecated members.
 */
class ApiWithLegacyMembers
{
    /**
     * A constant Drupal 12 removes.
     *
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
     *   ApiWithLegacyMembers::CURRENT instead.
     */
    public const EXISTS_REPLACE = 1;

    /**
     * A constant that stays.
     */
    public const CURRENT = 2;

    /**
     * A property Drupal 12 removes.
     *
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is
     *   no replacement.
     */
    protected array $legacyCache = [];

    /**
     * A static property Drupal 12 removes.
     *
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is
     *   no replacement.
     */
    public static int $legacyCount = 0;
}

/**
 * A factory Drupal 12 removes.
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is
 *   no replacement.
 */
class LegacyFactory
{
    /**
     * Creates something.
     */
    public static function make(): object
    {
        return new \stdClass();
    }

    /**
     * Creates something the old way.
     *
     * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. Use
     *   make() instead.
     */
    public static function makeOld(): object
    {
        return new \stdClass();
    }
}

/**
 * An exception Drupal 12 removes.
 *
 * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is
 *   no replacement.
 */
class LegacyException extends \Exception {}

/**
 * An interface one of whose methods Drupal 12 removes.
 */
interface ApiWithDeprecatedMethodInterface
{
    /**
     * Trusts the data.
     *
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is
     *   no replacement.
     */
    public function trust(): static;
}

/**
 * Implements the deprecated method without saying so.
 */
class ApiWithDeprecatedMethod implements ApiWithDeprecatedMethodInterface
{
    /**
     * {@inheritdoc}
     */
    public function trust(): static
    {
        return $this;
    }
}

/**
 * Keeps the method, which it says with `@not-deprecated`.
 */
class ApiKeepingMethod implements ApiWithDeprecatedMethodInterface
{
    /**
     * Trusts the data, and is not going away here.
     *
     * @not-deprecated
     */
    public function trust(): static
    {
        return $this;
    }
}

/**
 * Provides the method for a class that implements the interface.
 */
trait TrustTrait
{
    /**
     * Trusts the data.
     */
    public function trust(): static
    {
        return $this;
    }
}

/**
 * Implements the deprecated method through a trait.
 */
class ApiWithTraitMethod implements ApiWithDeprecatedMethodInterface
{
    use TrustTrait;
}

/**
 * Declares the method without the deprecated interface.
 */
class PlainTrust
{
    /**
     * Trusts the data.
     */
    public function trust(): static
    {
        return $this;
    }
}

/**
 * Adds the deprecated interface to a parent that declares the method.
 */
class InheritedTrust extends PlainTrust implements ApiWithDeprecatedMethodInterface {}

/**
 * An interface with a constant Drupal 12 removes.
 */
interface WithLegacyConstantInterface
{
    /**
     * A constant Drupal 12 removes.
     *
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is
     *   no replacement.
     */
    const OLD = 1;
}
