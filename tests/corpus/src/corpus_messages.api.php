<?php

/**
 * @file
 * Hook documentation, whose examples show messages as plain text.
 */

declare(strict_types=1);

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Sets each message that is reported outside hook documentation.
 */
function hook_corpus_messages(FormStateInterface $form_state, MessengerInterface $messenger): void {
  $form_state->setErrorByName('name', 'Enter a name.');
  $messenger->addStatus('Saved.');
}
