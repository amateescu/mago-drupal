<?php

declare(strict_types=1);

namespace Drupal\corpus;

/**
 * Catches exceptions in each shape the rule tells apart.
 */
final class NonCapturingCatch {

  /**
   * Nothing reads the variable.
   */
  public function unread(): void {
    try {
      $this->unread();
    }
    // @mago-expect lint:drupal/non-capturing-catch
    catch (\RuntimeException|\LogicException $e) {
    }
  }

  /**
   * The catch block reads it.
   */
  public function read(): string {
    try {
      $this->unread();
    }
    catch (\RuntimeException $e) {
      return $e->getMessage();
    }

    return '';
  }

  /**
   * The finally block and the code after the try read it.
   */
  public function readLater(): string {
    try {
      $this->unread();
    }
    catch (\RuntimeException $first) {
    }
    finally {
      $seen = isset($first);
    }

    try {
      $this->unread();
    }
    catch (\RuntimeException $second) {
    }

    return $seen ? (string) ($second ?? '') : '';
  }

  /**
   * A closure's use list reads it; a nested closure's body doesn't.
   */
  public function closures(): array {
    try {
      $this->unread();
    }
    catch (\RuntimeException $used) {
      $kept = function () use ($used): string {
        return $used->getMessage();
      };
    }

    try {
      $this->unread();
    }
    // @mago-expect lint:drupal/non-capturing-catch
    catch (\RuntimeException $shadowed) {
      $other = static function (\RuntimeException $shadowed): string {
        return $shadowed->getMessage();
      };
    }

    return [$kept ?? NULL, $other ?? NULL];
  }

  /**
   * An interpolated string reads it.
   */
  public function interpolated(): string {
    try {
      $this->unread();
    }
    catch (\RuntimeException $e) {
      return "Failed: {$e->getMessage()}";
    }

    return '';
  }

  /**
   * A compact() call may read it, so the rule leaves the function alone.
   */
  public function compacted(): array {
    try {
      $this->unread();
    }
    catch (\RuntimeException $e) {
    }

    return compact('e');
  }

}
