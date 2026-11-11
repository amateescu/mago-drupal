<?php

/**
 * @file
 * The form state signature that drupal/untranslated-message checks.
 */

declare(strict_types=1);

namespace Drupal\Core\Form;

interface FormStateInterface
{
    /**
     * @param string $name
     * @param string|\Stringable $message
     * @return $this
     */
    public function setErrorByName($name, $message = '');
}
