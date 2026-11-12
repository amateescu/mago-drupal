<?php

/**
 * @file
 * Holds constants with a blank line above them.
 */

const BLANK_LINE_FILE = 1;

/**
 * The limit.
 */

const BLANK_LINE_LIMIT = 10;

/**
 * The offset, right below its docblock.
 */
const BLANK_LINE_OFFSET = 0;

/**
 * @defgroup blank_line_group Blank line group
 * @{
 */

const BLANK_LINE_GROUPED = 2;

/**
 * @} End of "defgroup blank_line_group".
 */

const BLANK_LINE_AFTER_GROUP = 3;

class BlankLineConstants
{
    /**
     * The mode.
     */

    public const MODE = 'a';

    /**
     * The kind, with an attribute below the blank line.
     */

    #[Deprecated]
    final public const string KIND = 'b';
}

enum BlankLineCases
{
    /**
     * The first case.
     */

    case First;

    /**
     * The second case.
     */
    case Second;
}
