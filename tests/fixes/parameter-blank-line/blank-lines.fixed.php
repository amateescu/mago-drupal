<?php

/**
 * Has blank lines between, before and after its parameters.
 */
function lines(
    int $a,
    int $b,
    int $c,
): void {
}

/**
 * Has a blank line with spaces and tabs.
 */
function spaces(
    int $a,
    int $b,
    int $c,
): void {
}

/**
 * Has a blank line in an empty list.
 */
function empty_list(
): void {
}

/**
 * Has blank lines around comments.
 */
function comments(
    int $a, // The first.
    // The second.
    int $b,
    /* The third. */
    int $c,
): void {
}

/**
 * Has blank lines in a closure and a method.
 */
abstract class Lines
{
    /**
     * Has a blank line between its parameters.
     */
    abstract public function run(
        int $a,
        int $b,
    ): void;

    /**
     * Holds closures.
     */
    public function closures(int $x, int $y): array
    {
        $first = function (
            int $a,
            int $b,
        ) use ($x): int {
            return $a + $b + $x;
        };
        $second = function (
            int $a,
        )
        use (
            $x,
            $y,
        ): int {
            return $a + $x + $y;
        };

        return [$first, $second];
    }
}

/**
 * Has blank lines that the rule leaves alone.
 */
function kept(
    array $list = [
        1,

        2,
    ],
    string $text = <<<'TEXT'
        One

        Two
        TEXT,
    /*
     * One.
     *
     * Two.
     */
    int $b = 0,
): void {

    echo $b;

}
