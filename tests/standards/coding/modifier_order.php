// Before.
abstract class Example {

  public final function one(): void {}

  static public function two(): void {}

  protected abstract function three(): void;

}

// After.
abstract class Example {

  final public function one(): void {
  }

  public static function two(): void {
  }

  abstract protected function three(): void;

}
