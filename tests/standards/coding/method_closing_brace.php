// Before.
class Example {

  public function __construct() {
  }

  public function count(): int {
    return 0; }

  public function nothing(): void {}

}
$callback = function () {
};

// After.
class Example {

  public function __construct() {}

  public function count(): int {
    return 0;
  }

  public function nothing(): void {
  }

}

$callback = function () {};
