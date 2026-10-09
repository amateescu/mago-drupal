<?php

/**
 * Counts the rows.
 *
 * @param integer $limit
 *   The most rows to count.
 * @param boolean|NULL $strict
 *   Whether to count strictly.
 *
 * @return integer|int
 *   The count.
 */
function type_names_count($limit, $strict = NULL) {
  return $limit;
}

/**
 * Loads the ids.
 *
 * @param integer ...$ids
 *   The ids.
 *
 * @return array<integer|int>
 *   Coder does not look inside a generic.
 */
function type_names_load(...$ids) {
  return $ids;
}

/**
 * Checks a flag.
 *
 * @param mixed ...$args
 *   The arguments.
 *
 * @return Boolean
 *   The flag.
 */
function type_names_flag(...$args) {
  return $args !== [];
}
