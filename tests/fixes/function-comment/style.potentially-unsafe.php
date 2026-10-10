<?php

/*
 * Uses a block comment laid out like a docblock.
 */
function block_shaped(): void
{
}

/* Uses a one-line block comment. */
function block_one_line(): void
{
}

// Uses a line comment.
function line_comment(): void
{
}

#[\Deprecated]
// Uses a line comment below an attribute.
function line_comment_below_attribute(): void
{
}

$x = 1; // Trails the line above.
function trailing(): void
{
}
