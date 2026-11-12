// Before.
$plain = [
  'first' => 'one',
  'second' => 'two'
];
$hugged = ['#markup' => t('Some long text that goes here, and it goes on for a long while, past the limit: @count', ['@count' => 10])];
$nested = [['first long element goes here', 'second long element goes here', 'third long element goes here', 'fourth', 'fifth']];

// After.
$plain = [
  'first' => 'one',
  'second' => 'two',
];
$hugged = ['#markup' => t('Some long text that goes here, and it goes on for a long while, past the limit: @count', [
  '@count' => 10,
])];
$nested = [[
  'first long element goes here',
  'second long element goes here',
  'third long element goes here',
  'fourth',
  'fifth',
]];
