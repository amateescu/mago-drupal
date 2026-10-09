<?php

/**
 * Ends with a bare return.
 */
function plain(): void
{
    echo 'one';

}

/**
 * The only statement is a bare return.
 */
function only(): void
{
}

/**
 * The spacing inside the statement varies.
 */
function spacing(): void
{
    echo 'two';
}

/**
 * The semicolon is on the next line.
 */
function split(): void
{
    echo 'three';
}

/**
 * A comment on the line or below stays.
 */
function comments(): void
{
    echo 'four';
    // Done.
    // Nothing follows.
}

/**
 * The statement shares its line with other code.
 */
function inline(): void { }

/**
 * A closure, a static closure and a method.
 */
class Returns
{
    /**
     * Ends with a bare return.
     */
    public function method(): void
    {
        echo 'five';
    }

    /**
     * Holds closures.
     */
    public function closures(): array
    {
        $plain = function (): void {
            echo 'six';
        };
        $static = static function (): void {
        };

        return [$plain, $static];
    }
}

/**
 * The return ends a block that ends the body.
 */
function block(): void
{
    echo 'seven';
    {
    }
}

/**
 * A comment inside the statement is reported and left alone.
 */
function commentInside(): void
{
    echo 'eight';
    return /* Done. */;
}

/**
 * These are not at the end of the body.
 */
function kept(int $value): ?int
{
    if ($value > 1) {
        return;
    }

    foreach ([1, 2] as $item) {
        return null;
    }

    while ($value) return;

    return $value;
}
