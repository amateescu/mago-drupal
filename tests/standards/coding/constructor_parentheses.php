// Before.
$foo = new MyClassName;
$bar = new $className;

// After.
$foo = new MyClassName();
$bar = new $className();
