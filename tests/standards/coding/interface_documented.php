// Reported.
interface ExampleInterface {

  public function count(): int;

}

// Fine.
/**
 * Counts things.
 */
interface ExampleInterface {

  /**
   * Returns the count.
   */
  public function count(): int;

}
