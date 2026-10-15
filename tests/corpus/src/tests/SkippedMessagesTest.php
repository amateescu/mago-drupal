<?php

declare(strict_types=1);

// Messages in test code, which the check leaves alone.
namespace Drupal\Tests\corpus;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;

/**
 * Sets each message that is reported outside tests.
 */
final class SkippedMessagesTest {

  /**
   * Tests check the text they set, so nothing here is reported.
   */
  public function testMessages(FormStateInterface $form_state, MessengerInterface $messenger): void {
    $form_state->setErrorByName('name', 'Enter a name.');
    $messenger->addStatus('Saved.');
  }

}
