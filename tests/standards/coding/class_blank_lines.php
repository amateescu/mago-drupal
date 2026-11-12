// Before.
interface FelineInterface {
  public function meow();
  public function eatLasagna($amount);
}
class GarfieldTheCat implements FelineInterface {
  protected $lasagnaEaten = 0;
  public function meow() {
    return 'Meow';
  }
  public function eatLasagna($amount) {
    $this->lasagnaEaten += $amount;
  }
}

// After.
interface FelineInterface {

  public function meow();

  public function eatLasagna($amount);

}

class GarfieldTheCat implements FelineInterface {

  protected $lasagnaEaten = 0;

  public function meow() {
    return 'Meow';
  }

  public function eatLasagna($amount) {
    $this->lasagnaEaten += $amount;
  }

}
