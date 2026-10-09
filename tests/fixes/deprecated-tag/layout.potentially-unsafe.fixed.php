<?php

/**
 * Uses an old wording with a short version.
 *
 * @deprecated in drupal:8.0.0 and is removed from drupal:9.0.0. Use bar() instead.
 *
 * @see https://www.drupal.org/node/3456789
 */
function deprecated_old_wording(): void
{
}

/**
 * Uses "as of", with the text on two lines.
 *
 * @deprecated in drupal:8.5.0 and is removed from drupal:9.0.0.
 *   Use bar() instead.
 *
 * @see https://www.drupal.org/node/3456789
 */
function deprecated_as_of(): void
{
}

/**
 * Has the text on the line below the tag, and versions with one part.
 *
 * @deprecated
 *   in drupal:8.3.0 and is removed from drupal:9.0.0. Use bar().
 *
 * @see https://www.drupal.org/node/3456789
 */
function deprecated_next_line(): void
{
}

/**
 * Starts with text that the fix drops.
 *
 * @deprecated in drupal:8.6.0 and is removed from drupal:9.0.0. Use bar().
 *
 * @see https://www.drupal.org/node/3456789
 */
function deprecated_prefix(): void
{
}

/**
 * Has no versions to read, which has no fix.
 *
 * @deprecated Use bar() instead.
 *
 * @see https://www.drupal.org/node/3456789
 */
function deprecated_no_versions(): void
{
}
