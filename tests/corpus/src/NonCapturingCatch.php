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

  /**
   * A reference parameter passes the exception to the caller.
   */
  public function referenceParameter(?\Throwable &$error): void {
    try {
      $this->unread();
    }
    // @mago-expect lint:drupal/variable-redeclaration
    catch (\RuntimeException $error) {
    }
  }

  /**
   * A comment after the `&` keeps the parameter a reference.
   */
  public function referenceParameterComment(?\Throwable &/* shared */ $error): void {
    try {
      $this->unread();
    }
    // @mago-expect lint:drupal/variable-redeclaration
    catch (\RuntimeException $error) {
    }
  }

  /**
   * A comment after the `&` of a foreach target or closure variable.
   */
  public function referenceComments(): \Closure {
    $items = ['first', 'second'];
    foreach ($items as & /* shared */ $item) {
    }
    $last = function () use (&/* shared */ $error): mixed {
      return $error;
    };
    try {
      $this->unread();
    }
    catch (\RuntimeException $item) {
    }
    try {
      $this->unread();
    }
    catch (\RuntimeException $error) {
    }

    return $last;
  }

  /**
   * A variadic reference parameter is a new array, which the catch replaces.
   */
  public function variadicReference(mixed &...$error): void {
    try {
      $this->unread();
    }
    // @mago-expect lint:drupal/non-capturing-catch
    // @mago-expect lint:drupal/variable-redeclaration
    catch (\RuntimeException $error) {
    }
  }

  /**
   * A closure that binds the name by reference reads what the catch writes.
   */
  public function capturedByReference(): \Closure {
    $last = function () use (&$error): mixed {
      return $error;
    };
    try {
      $this->unread();
    }
    catch (\RuntimeException $error) {
    }

    return $last;
  }

  /**
   * A closure that binds the name by value keeps the value from before.
   */
  public function capturedByValue(): \Closure {
    $error = NULL;
    $last = function () use ($error): mixed {
      return $error;
    };
    try {
      $this->unread();
    }
    // @mago-expect lint:drupal/non-capturing-catch
    catch (\RuntimeException $error) {
    }

    return $last;
  }

  /**
   * The next pass of the loop reads the exception of the one before.
   */
  public function loop(int $count): int {
    $error = NULL;
    $failed = 0;
    for ($i = 0; $i < $count; $i++) {
      if ($error !== NULL) {
        $failed++;
      }
      try {
        $this->unread();
      }
      catch (\RuntimeException $error) {
      }
    }

    return $failed;
  }

  /**
   * A global name, a static name and a reference share the value.
   */
  public function shared(array &$items): void {
    global $_corpus_error;
    static $last;
    $bound = NULL;
    $items[] = &$bound;
    try {
      $this->unread();
    }
    // @mago-expect lint:drupal/variable-redeclaration
    catch (\RuntimeException $_corpus_error) {
    }
    try {
      $this->unread();
    }
    // @mago-expect lint:drupal/variable-redeclaration
    catch (\RuntimeException $last) {
    }
    try {
      $this->unread();
    }
    catch (\RuntimeException $bound) {
    }
  }

}
