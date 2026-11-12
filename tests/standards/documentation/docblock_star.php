// Before.
/**
 * Returns one.

   The long description doesn't have a star.
 */
function docblock_star_one(): int {
  return 1;
}

// After.
/**
 * Returns one.
 *
 * The long description doesn't have a star.
 */
function docblock_star_one(): int {
  return 1;
}
