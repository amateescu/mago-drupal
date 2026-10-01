<?php

namespace Drupal\example {

    function braced(): string
    {
        return \Drupal\Core\Url::fromRoute('x')->toString();
    }
}
