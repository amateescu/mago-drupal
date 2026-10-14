<?php

/**
 * Has the description on the tag's line.
 *
 * @throws \RuntimeException
 *   Failed to load the file.
 */
function throws_same_line(): void
{
}

/**
 * Has a union type, a tab and a blank line below.
 *
 * @throws \LogicException|\RuntimeException
 *   Thrown when the input is bad.
 *
 * @return int
 *   The count.
 */
function throws_union(): int
{
    return 0;
}

/**
 * Has the closer on the tag's line, which the fix leaves alone.
 *
 * @throws \RuntimeException Failed to load the file. */
function throws_closer(): void
{
}

/**
 * Has a type and nothing else, which has no description to move.
 *
 * @throws \Foo2Bar
 */
function throws_type_only(): void
{
}
