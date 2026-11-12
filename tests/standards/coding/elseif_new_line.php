// Before.
if ($a) {
  one();
} elseif ($b) {
  two();
} else {
  three();
}
try {
  four();
} catch (\Exception $e) {
  five();
} finally {
  six();
}

// After.
if ($a) {
  one();
}
elseif ($b) {
  two();
}
else {
  three();
}
try {
  four();
}
catch (\Exception $e) {
  five();
}
finally {
  six();
}
