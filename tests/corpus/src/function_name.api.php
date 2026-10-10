<?php

/**
 * @file
 * Exercises drupal/function-name in an API file.
 */

declare(strict_types=1);

/**
 * Documents a hook with a placeholder in its name, which Coder accepts.
 */
function hook_ENTITY_TYPE_insert(): void {
}

/**
 * Has a camelCase name, which Coder reports in an API file too.
 */
// @mago-expect lint:drupal/function-name
function functionNameInApiFile(): void {
}
