<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Writes TRUE, FALSE and NULL with a leading backslash.
 */
class LiteralConstantCase {

  // @mago-expect lint:drupal/literal-constant-case
  public const ENABLED = \true;

  // @mago-expect lint:drupal/literal-constant-case
  /**
   * Reads each one in lower and mixed case.
   */
  public function lowerCase(?bool $flag = \false): ?bool {
    // @mago-expect lint:drupal/literal-constant-case
    if ($flag === \False) {
      // @mago-expect lint:drupal/literal-constant-case
      return \null;
    }

    return $flag;
  }

  /**
   * Upper case with the backslash, and the plain forms, are fine.
   */
  public function upperCase(?bool $flag = \FALSE): array {
    return [$flag, \TRUE, \NULL, TRUE, FALSE, NULL];
  }

}
