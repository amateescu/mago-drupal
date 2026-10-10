<?php

namespace Drupal\example;

// Holds the example settings.
// Two lines of them.
final class LineComment
{
}

/*
 * Holds the example block.
 */
final class BlockComment
{
}

#[\Attribute]
// Holds the example attribute.
final class BelowAttribute
{
}

$x = 1; // A trailing comment of the line above.
final class Trailing
{
}

// A section label, parted by a blank line.

final class Parted
{
}
