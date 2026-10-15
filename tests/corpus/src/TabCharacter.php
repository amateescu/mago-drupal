<?php

/**
 * @file
 * Exercises the rule about tab characters.
 */

declare(strict_types=1);

/**
 * Holds tabs in comments.
 */
function tab_character_comments(): int {
  // @mago-expect lint:drupal/tab-character
  // A comment	with a tab.
  $count = 1;

  // @mago-expect lint:drupal/tab-character
  /* A block	comment. */
  return $count;
}

// @mago-expect lint:drupal/tab-character
/**
 * Holds	a tab in its summary.
 */
function tab_character_docblock(): void {
}

/**
 * Holds a tab in a string only.
 */
function tab_character_fine(): string {
  return "A\tB";
}

// @mago-format-ignore-start
/**
 * Prints a list.
 */
function tab_character_html(): void {
  // @mago-expect lint:drupal/tab-character
  ?>
	<ul><li>Item</li></ul>
  <?php
}
// @mago-format-ignore-end
