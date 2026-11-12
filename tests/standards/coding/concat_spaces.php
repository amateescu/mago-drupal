// Before.
$string = 'Foo'.$bar;
$string = $bar.'foo';
$string = bar().'foo';

// After.
$string = 'Foo' . $bar;
$string = $bar . 'foo';
$string = bar() . 'foo';
