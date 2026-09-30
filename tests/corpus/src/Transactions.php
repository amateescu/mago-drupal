<?php

/**
 * @file
 * Transactions committed by going out of scope, and the shapes left alone.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Database\Connection;
use Drupal\Core\Database\Transaction;

/**
 * Runs work inside database transactions.
 */
final class Transactions {

  /**
   * A transaction kept on the object, which other methods commit.
   */
  private ?Transaction $transaction = NULL;

  public function __construct(
    private readonly Connection $database,
  ) {}

  /**
   * Committed explicitly, the shape Drupal asks for.
   */
  public function committed(): void {
    $transaction = $this->database->startTransaction();
    $this->work();
    $transaction->commitOrRelease();
  }

  /**
   * Rolled back on failure but committed by the destructor otherwise.
   */
  public function outOfScope(): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    try {
      $this->work();
    }
    catch (\Exception $e) {
      $transaction->rollBack();
      throw $e;
    }
  }

  /**
   * Unsetting the variable commits the same way.
   */
  public function unsetCommits(): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    $this->work();
    $transaction->name();
    unset($transaction);
  }

  /**
   * A result nobody keeps commits at once.
   */
  public function discarded(): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $this->database->startTransaction();
    $this->work();
  }

  /**
   * The closure is the scope of its own variable.
   */
  public function inClosure(): void {
    $this->run(function (): void {
      // @mago-expect analysis:drupal/implicit-transaction-commit
      $transaction = $this->database->startTransaction();
      $this->work();
      $transaction->name();
    });
  }

  /**
   * The caller commits a returned transaction.
   */
  public function returned(): Transaction {
    return $this->database->startTransaction();
  }

  /**
   * A variable passed on may be committed by the callee.
   */
  public function passedOn(): void {
    $transaction = $this->database->startTransaction();
    $this->finish($transaction);
  }

  /**
   * A variable returned later is committed by the caller.
   */
  public function returnedLater(): Transaction {
    $transaction = $this->database->startTransaction();
    $this->work();

    return $transaction;
  }

  /**
   * A transaction stored on the object outlives the method.
   */
  public function stored(): void {
    $this->transaction = $this->database->startTransaction();
  }

  /**
   * A captured variable may be committed inside the closure.
   */
  public function captured(): void {
    $transaction = $this->database->startTransaction();
    $this->run(function () use ($transaction): void {
      $this->work();
      $transaction->commitOrRelease();
    });
  }

  /**
   * Does the work of a transaction.
   */
  private function work(): void {
  }

  /**
   * Runs a callback.
   */
  private function run(\Closure $callback): void {
    $callback();
  }

  /**
   * Commits a transaction started elsewhere.
   */
  private function finish(Transaction $transaction): void {
    $transaction->commitOrRelease();
    $this->transaction?->commitOrRelease();
  }

}
