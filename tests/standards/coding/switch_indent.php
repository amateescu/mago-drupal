// Before.
switch ($condition) {
case 1:
one();
break;

default:
two();
}

// After.
switch ($condition) {
  case 1:
    one();
    break;

  default:
    two();
}
