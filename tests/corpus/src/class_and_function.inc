<?php

// @mago-expect lint:drupal/file-comment
/**
 * Shares its file with a function.
 */
final class ClassAndFunction {}

/**
 * Makes the file need a docblock of its own.
 */
function class_and_function_helper(): void {
}
