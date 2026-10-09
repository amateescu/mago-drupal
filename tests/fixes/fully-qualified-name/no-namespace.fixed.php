<?php

use Drupal\Core\Url;

function no_namespace(): string
{
    return Url::fromRoute('x')->toString();
}
