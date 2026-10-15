<?php

/**
 * Takes an intersection type with a null default. PHP 8.1 has no way to
 * write it nullable, so the parameter keeps its type.
 */
function intersection_type(\Countable&\Traversable $items = NULL): void
{
}
