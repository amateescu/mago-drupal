// Before.
if ($a) {
      one();
	if ($b) {
    two();
	}
}

// After.
if ($a) {
  one();
  if ($b) {
    two();
  }
}
