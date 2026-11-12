// Before.
$callback = function(array $items) use ($limit) {
  return count($items) > $limit;
};

// After.
$callback = function (array $items) use ($limit) {
  return count($items) > $limit;
};
