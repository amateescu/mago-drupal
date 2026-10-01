<?php

namespace Drupal\example;

use Drupal\Core\Url;
use Drupal\node\NodeInterface as Node;

final class Imports
{
    public function build(\Drupal\Core\Lock\LockBackendInterface $lock): \Drupal\Core\Lock\LockBackendInterface
    {
        $url = \Drupal\Core\Url::fromRoute('x');
        $node = \Drupal\node\NodeInterface::class;
        $same = new \Drupal\example\Helper();
        $value = \Drupal\Core\Site\Settings::get('x');
        $constant = \Drupal\example\Thing::NAME;
        $callable = \Drupal\example\helper(...);
        $limit = \Drupal\other\LIMIT;

        return $lock;
    }
}
