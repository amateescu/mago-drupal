<?php

/**
 * @file
 * The Views SQL query methods, as core documents them.
 */

declare(strict_types=1);

namespace Drupal\views\Plugin\views\query {
    class Sql
    {
        /**
         * @param string $group
         * @param string $field
         * @param string|array|null $value
         * @param string|null $operator
         */
        public function addWhere($group, $field, $value = null, $operator = null) {}

        /**
         * @param string $group
         * @param string $snippet
         * @param array $args
         */
        public function addWhereExpression($group, $snippet, $args = []) {}
    }
}
