<?php

/**
 * From PHP 8.4, Mago's own explicit-nullable-param reports this parameter,
 * and the rule leaves it alone.
 */
function newer_target(int $id = NULL): void
{
}
