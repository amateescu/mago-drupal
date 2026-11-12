// Before.
class Example {

  public function count ( int $a, int $b ): int {
    return $a + $b;
  }

}

// After.
class Example {

  public function count(int $a, int $b): int {
    return $a + $b;
  }

}
