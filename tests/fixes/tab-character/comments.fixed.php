<?php

/**
 * Holds    tabs    in its summary.
 *
 * @code
 * $a    = 1;
 * @endcode
 */
function tabs(): int {
  // A comment    with a tab.
  $count = 1;	// A trailing    comment.
  /*
	 * An indent that the formatter writes.
   *    A tab after the star.
   */
  return $count + strlen("a\tb");
}

function html(): void {
  ?>
	<p>Kept by a safe run.</p>
  <?php
}
