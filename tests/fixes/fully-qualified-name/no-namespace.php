<?php

function no_namespace(): string
{
    return \Drupal\Core\Url::fromRoute('x')->toString();
}
