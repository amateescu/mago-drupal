// Before.
try {
  one();
} catch (\Exception $e) {
  two();
}

// After.
try {
  one();
}
catch (\Exception $e) {
  two();
}
