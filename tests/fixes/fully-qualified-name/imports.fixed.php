<?php

namespace Drupal\example;

use Drupal\Core\Url;
use Drupal\node\NodeInterface as Node;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\Site\Settings;

final class Imports
{
    public function build(LockBackendInterface $lock): LockBackendInterface
    {
        $url = Url::fromRoute('x');
        $node = Node::class;
        $same = new Helper();
        $value = Settings::get('x');
        $constant = Thing::NAME;
        $callable = \Drupal\example\helper(...);
        $limit = \Drupal\other\LIMIT;

        return $lock;
    }
}
