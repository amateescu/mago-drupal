<?php

declare(strict_types=1);

namespace Drupal\corpus;

use function t;

/**
 * Exercises the DrupalPractice rules ported as native linter rules.
 */
class Practice {

  // @mago-expect lint:drupal/missing-function-comment
  public function translatedException(): never {
    // Both rules fire here.
    // The message is translated, and t() is the procedural form of
    // $this->t().
    // @mago-expect lint:drupal/translated-exception
    // @mago-expect lint:drupal/global-function
    // Under strict types the markup object is also a TypeError.
    // @mago-expect analysis:invalid-argument
    throw new \RuntimeException(t('This should not be translated.'));
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function plainException(): never {
    throw new \RuntimeException('This is fine.');
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function translatedAnonymousException(): never {
    // The rule reads the constructor arguments of an anonymous class too.
    // @mago-expect lint:drupal/translated-exception
    throw new class($this->t('This should not be translated.')) extends \RuntimeException {};
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function plainAnonymousException(): never {
    throw new class('This is fine.') extends \RuntimeException {};
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function translatedExceptionOnLaterLine(): never {
    // @mago-expect lint:drupal/translated-exception(1)
    throw new \RuntimeException(
      $this->t('This should not be translated.'),
    );
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function translatedExceptionPinnedToTheThrowLine(int $code): never {
    // The issue starts at `throw`, as in Coder, so a phpcs:ignore comment
    // written for Coder sits above the same line. The pin covers the first
    // line only, because its range ends at the `{`.
    // @mago-expect lint:drupal/translated-exception(1)
    throw match ($code) {
      1 => new \LogicException($this->t('This should not be translated.')),
      default => new \RuntimeException('This is fine.'),
    };
  }

  /**
   * Stands in for StringTranslationTrait::t().
   */
  protected function t(string $string): string {
    return $string;
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function translationMethodIsNotProcedural(): string {
    // $this->t() reports the same callee name as t().
    // It is the fix this rule recommends, so it must never be flagged.
    return $this->t('Already correct.');
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function unserializing(string $payload): void {
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($payload);
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($payload, ['max_depth' => 2]);
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($payload, ['allowed_classes' => TRUE]);
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($payload, array('allowed_classes' => TRUE));
    // A leading backslash names the same constant.
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($payload, ['allowed_classes' => \TRUE]);
    // @mago-expect lint:drupal/insecure-unserialize
    // @mago-expect lint:drupal/literal-constant-case
    unserialize($payload, ['allowed_classes' => \true]);
    // Parentheses don't change the value.
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($payload, ['allowed_classes' => TRUE]);
    // PHP reads only the options' own keys, not a nested array's.
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($payload, ['nested' => ['allowed_classes' => FALSE]]);
    // A first-class callable passes the call on with no options.
    // @mago-expect lint:drupal/insecure-unserialize
    array_map(unserialize(...), [$payload]);

    unserialize($payload, ['allowed_classes' => FALSE]);
    unserialize($payload, array('allowed_classes' => FALSE));
    unserialize($payload, ['allowed_classes' => [self::class]]);

    // PHP binds this by parameter name, so the options are there.
    unserialize($payload, options: ['allowed_classes' => FALSE]);

    // The rule cannot see into a variable, and says so.
    $options = ['allowed_classes' => FALSE];
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($payload, $options);

    // A payload this function serialized itself carries no foreign class.
    unserialize(serialize($payload));
    $serialized = serialize([self::class]);
    unserialize($serialized);

    // A variable that is also written from elsewhere is not trusted.
    $mixed = serialize($payload);
    $mixed = $payload;
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($mixed);

    // An arrow function reads the payload this function serialized.
    $captured = serialize([self::class]);
    array_map(fn () => unserialize($captured), [1]);
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function unserializingOtherScopes(): array {
    global $_corpus_shared;
    assert(is_string($_corpus_shared));
    // A closure's assignment writes to a variable of its own.
    $closure = function () {
      $_corpus_shared = serialize([]);
      return $_corpus_shared;
    };
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($_corpus_shared);

    // An arrow function's assignment doesn't leave the arrow function.
    $arrow = fn () => $_corpus_shared = serialize([]);
    // @mago-expect lint:drupal/insecure-unserialize
    unserialize($_corpus_shared);

    // A parameter of the arrow function hides the serialized variable.
    $serialized = serialize([self::class]);
    // @mago-expect lint:drupal/insecure-unserialize
    array_map(fn ($serialized) => unserialize($serialized), [$serialized]);

    // A default value that only writes the name doesn't hide the variable.
    $named = serialize([self::class]);
    array_map(fn ($label = '$named') => unserialize($named), [1]);

    // An arrow function copies the value when PHP creates it, so a later
    // assignment doesn't reach the read.
    global $_corpus_late;
    assert(is_string($_corpus_late));
    // @mago-expect lint:drupal/insecure-unserialize
    $late = fn () => unserialize($_corpus_late);
    $_corpus_late = serialize([]);
    // An assignment before the arrow function, or inside it, does.
    $early = fn () => unserialize($_corpus_late);
    $inside = fn () => [$_corpus_inside = serialize([]), unserialize($_corpus_inside)];

    return [$closure, $arrow, $late, $early, $inside];
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function upperCaseBranching(int $value): int {
    if ($value === 1) {
      return 1;
    }
    // PHP keywords are case-insensitive, so this is the same construct.
    // @mago-expect lint:drupal/else-if
    else if ($value === 2) {
      return 2;
    }

    return 0;
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function branching(int $value): int {
    if ($value === 1) {
      return 1;
    }
    // @mago-expect lint:drupal/else-if
    else if ($value === 2) {
      return 2;
    }
    elseif ($value === 3) {
      return 3;
    }
    else {
      // A braced nested if is a different construct.
      if ($value === 4) {
        return 4;
      }
    }

    return 0;
  }

  // @mago-expect lint:drupal/missing-function-comment
  public function globalInClassMethod(): void {
    // drupal/global-variable checks a class method too.
    // @mago-expect lint:drupal/global-variable
    global $corpus_unprefixed;

    $corpus_unprefixed = [];
  }

}
