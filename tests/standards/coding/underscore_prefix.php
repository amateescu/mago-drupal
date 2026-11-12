// Reported.
class Example {

  protected int $_count = 0;

  protected function _count(): int {
    return $this->_count;
  }

}

// Fine.
class Example {

  protected int $count = 0;

  protected function count(): int {
    return $this->count;
  }

}
