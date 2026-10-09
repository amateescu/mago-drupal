<?php

/**
 * Ends with a bare return.
 */
function plain(): void
{
    echo 'one';

    return;
}

/**
 * The only statement is a bare return.
 */
function only(): void
{
    return;
}

/**
 * The spacing inside the statement varies.
 */
function spacing(): void
{
    echo 'two';
    return ;
}

/**
 * The semicolon is on the next line.
 */
function split(): void
{
    echo 'three';
    return
        ;
}

/**
 * A comment on the line or below stays.
 */
function comments(): void
{
    echo 'four';
    return; // Done.
    // Nothing follows.
}

/**
 * The statement shares its line with other code.
 */
function inline(): void { return; }

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
        return;
    }

    /**
     * Holds closures.
     */
    public function closures(): array
    {
        $plain = function (): void {
            echo 'six';
            return;
        };
        $static = static function (): void {
            return;
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
        return;
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
