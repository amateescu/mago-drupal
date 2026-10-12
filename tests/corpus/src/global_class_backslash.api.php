<?php

/**
 * @file
 * Hooks of the corpus, with examples that leave out their imports.
 */

/**
 * Acts on the state.
 */
function hook_global_class_backslash_state(): void {
  $state = Drupal::state();
  $state->get('corpus');
}
