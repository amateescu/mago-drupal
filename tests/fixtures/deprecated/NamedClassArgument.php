<?php

namespace Drupal\fixture;

/**
 * Calls a method with a `class:` named argument before its deprecated members.
 */
class NamedClassArgument
{
    /**
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
     *   CURRENT instead.
     */
    public const BEFORE = 1;

    public function build(): void
    {
        self::helper(class: 1);
    }

    public static function helper(int $class): int
    {
        return $class;
    }

    /**
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
     *   CURRENT instead.
     */
    public const AFTER = 2;

    /**
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. There is
     *   no replacement.
     */
    protected array $legacy = [];
}
