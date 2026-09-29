<?php

/**
 * @file
 * Conditions added to a Views SQL query.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\views\Plugin\views\query\Sql;

/**
 * Adds conditions to the default group, which core numbers 0.
 */
final class ViewsQueries {

  /**
   * The group takes the 0 core's docblock asks for.
   */
  public function defaultGroup(Sql $query): void {
    $query->addWhere(0, 'node.nid', 'value');
    $query->addWhereExpression(0, 'node.nid > 1');
    $query->addWhere('named', 'node.nid', NULL, 'IS NULL');
  }

  /**
   * The other parameters keep their declared types.
   */
  public function wrongField(Sql $query): void {
    // @mago-expect analysis:invalid-argument
    $query->addWhere(0, 42);
  }

}
