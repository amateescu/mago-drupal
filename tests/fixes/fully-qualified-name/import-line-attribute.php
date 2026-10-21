<?php

namespace Drupal\example;

use Drupal\Core\Url; #[\Attribute]
final class Probe
{
    public function build(): Url
    {
        return new \Drupal\Core\Lock\NullLockBackend() ? new Url() : new Url();
    }
}
