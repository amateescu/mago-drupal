<?php

namespace Drupal\example;

use Drupal\Component\Utility\Html;

/**
 * Builds labels the fix leaves alone.
 */
class Labels
{
    public function build(string $label): array
    {
        return [
            // Something follows the escape.
            $this->t('@label', ['@label' => Html::escape($label) . ' (draft)']),
            // A comment inside the parentheses.
            $this->t('@label', ['@label' => Html::escape(/* Trusted. */ $label)]),
            // A named argument.
            $this->t('@label', ['@label' => Html::escape(text: $label)]),
            // A ! placeholder needs the escape.
            $this->t('!label', ['!label' => Html::escape($label)]),
        ];
    }
}
