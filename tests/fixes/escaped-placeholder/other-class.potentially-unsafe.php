<?php

namespace Drupal\example;

/**
 * Calls an Html class of its own namespace, not Drupal's.
 */
class OtherClass
{
    public function build(string $label): string
    {
        return (string) $this->t('@label', ['@label' => Html::escape($label)]);
    }
}
