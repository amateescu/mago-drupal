<?php

/**
 * @file
 * The messenger signatures that drupal/untranslated-message checks.
 */

declare(strict_types=1);

namespace Drupal\Core\Messenger;

interface MessengerInterface
{
    /**
     * @param string|\Stringable $message
     * @return $this
     */
    public function addMessage($message, $type = 'status', $repeat = false);

    /**
     * @param string|\Stringable $message
     * @return $this
     */
    public function addStatus($message, $repeat = false);
}
