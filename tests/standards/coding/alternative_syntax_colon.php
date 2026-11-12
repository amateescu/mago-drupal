// Before.
if ($a) :
  one();
elseif ($b) :
  two();
endif;
foreach ($items as $item) :
  three($item);
endforeach;

// After.
if ($a):
  one();
elseif ($b):
  two();
endif;
foreach ($items as $item):
  three($item);
endforeach;
