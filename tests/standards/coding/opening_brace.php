// Before.
if ($a)
{
  one();
}
foreach ($items as $item){
  two($item);
}

// After.
if ($a) {
  one();
}
foreach ($items as $item) {
  two($item);
}
