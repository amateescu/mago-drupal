// Reported.
switch ($condition) {
  /* One. */ case 1:
    one();
    break;

  default:
    two();
}

// Fine.
switch ($condition) {
  // One.
  case 1:
    one();
    break;

  default:
    two();
}
