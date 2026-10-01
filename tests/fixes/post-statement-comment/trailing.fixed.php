<?php
// The opening tag line.

function trailing(array $items): int
{
    // Counts the items.
    $count = count($items);
    // Takes the first item.
    $first = reset($items);
    if ($count > 1) { // Opens a block.
        $count--;
    }
    $sum = array_sum(
        $items,
    ); // Closes a call.
    $total = $sum + 1; // Adds one,
    // which the next line explains.
    $text = <<<EOT
      Some text.
      EOT; // Ends a heredoc.
    $flag = TRUE; // phpcs:ignore Some.Sniff
    $value = 1; // cspell:disable-line
    // @phpstan-ignore argument.type
    $checked = intdiv($value, 2); // Divides it.
    $covered = 2; // @codeCoverageIgnore

    // Returns the sum.
    return $count + $first + $total;
}

class Trailing
{
    /**
     * The count.
     */
    public int $count = 0; // Starts at zero.
}
