<?php

/**
 * Lowercase start, which gets a capital.
 */
function capital_short(): void
{
}

/**
 * #123: Starts with an issue number, which has no fix.
 */
function capital_hash(): void
{
}

/**
 * 3 items, which has no fix.
 */
function capital_digit(): void
{
}

/**
 * Has a fine summary.
 *
 * Lowercase long description, which gets a capital.
 */
function capital_long(): void
{
}

/**
 * Has a fine summary.
 *
 * #hash at the start of the long description, which Coder accepts.
 */
function capital_long_hash(): void
{
}

/**
 * élan, a multi-byte first letter, which Coder accepts.
 */
function capital_multibyte(): void
{
}
