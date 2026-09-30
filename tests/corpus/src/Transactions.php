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
   * An explicit rollback ends the transaction without an implicit commit.
   */
  public function rolledBack(): void {
    $transaction = $this->database->startTransaction();
    $this->work();
    $transaction->rollBack();
  }

  /**
   * Both operations can sit inside the same conditional block.
   */
  public function rolledBackInBranch(bool $run): void {
    if ($run) {
      $transaction = $this->database->startTransaction();
      $transaction->rollBack();
    }
  }

  /**
   * Reusing the variable implicitly commits its earlier transaction.
   */
  public function overwrittenBeforeRollback(): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    $transaction = $this->database->startTransaction('second');
    $transaction->rollBack();
  }

  /**
   * Unsetting the first transaction also commits it implicitly.
   */
  public function unsetBeforeRollback(): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    unset($transaction);
    $transaction = $this->database->startTransaction('second');
    $transaction->rollBack();
  }

  /**
   * A conditional rollback leaves another path to the destructor.
   */
  public function conditionalRollback(bool $rollback): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    if ($rollback) {
      $transaction->rollBack();
    }
  }

  /**
   * An unbraced conditional rollback is conditional too.
   */
  public function unbracedRollback(bool $rollback): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    if ($rollback)
      $transaction->rollBack();
  }

  /**
   * An early return can skip a later rollback in the same block.
   */
  public function rollbackAfterEarlyReturn(bool $leave): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    if ($leave) {
      return;
    }
    $transaction->rollBack();
  }

  /**
   * A rollback inside a short-circuit expression may never run.
   */
  public function shortCircuitRollback(bool $rollback): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    // @mago-expect analysis:redundant-logical-operation
    $rollback && $transaction->rollBack();
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
   * A closure's own variable of the same name is another transaction.
   */
  public function nestedOwnVariable(): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction();
    $this->run(function (): void {
      $transaction = $this->database->startTransaction('inner');
      $transaction->commitOrRelease();
    });
    $transaction->name();
  }

  /**
   * An arrow function shares the variable, so it commits this transaction.
   */
  public function arrowCommits(): void {
    $transaction = $this->database->startTransaction();
    $commit = fn () => $transaction->commitOrRelease();
    $commit();
  }

  /**
   * A rollback before the start belongs to the earlier transaction.
   */
  public function rolledBackThenRestarted(): void {
    $transaction = $this->database->startTransaction();
    $transaction->rollBack();
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $transaction = $this->database->startTransaction('second');
    $this->work();
  }

  /**
   * A nullsafe call whose result nobody keeps commits at once too.
   */
  public function nullsafeDiscarded(?Connection $connection): void {
    // @mago-expect analysis:drupal/implicit-transaction-commit
    $connection?->startTransaction();
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
