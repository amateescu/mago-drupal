// Before.
class Example {

  public int $count = 0;
  public function count(): int {
    return $this->count;
  }

}

// After.
class Example {

  public int $count = 0;

  public function count(): int {
    return $this->count;
  }

}
