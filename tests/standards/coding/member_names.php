// Reported.
class Example {

  public int $item_count = 0;

  public function get_count(): int {
    return $this->item_count;
  }

}

// Fine.
class Example {

  public int $itemCount = 0;

  public function getCount(): int {
    return $this->itemCount;
  }

}
