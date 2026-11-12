// Before.
if($a) {
  one();
}
while  ($b) {
  two();
}
foreach($items as $item) {
  three($item);
}

// After.
if ($a) {
  one();
}
while ($b) {
  two();
}
foreach ($items as $item) {
  three($item);
}
