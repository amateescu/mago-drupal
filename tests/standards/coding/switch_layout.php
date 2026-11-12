// Reported.
switch ($condition) {
  case 1: one();
    break;
  case 2;
    two();
    break;
  default:
    three();
}

// Fine.
switch ($condition) {
  case 1:
    one();
    break;

  case 2:
    two();
    break;

  default:
    three();
}
