// Before.
$query = $connection->select('node', 'n')
        ->fields('n', ['nid', 'title', 'created', 'changed'])
        ->condition('n.status', 1)
        ->orderBy('n.created', 'DESC');

// After.
$query = $connection
  ->select('node', 'n')
  ->fields('n', ['nid', 'title', 'created', 'changed'])
  ->condition('n.status', 1)
  ->orderBy('n.created', 'DESC');
