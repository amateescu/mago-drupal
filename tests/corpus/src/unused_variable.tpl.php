<?php

/**
 * @file
 * A template. Coder's ruleset doesn't check templates for unused variables.
 */

$template_unused = 1;

/**
 * Writes a local in a template.
 */
function unused_variable_template_helper(): void {
  $unused = 1;
}

?>
<div><?php print 'x'; ?></div>
