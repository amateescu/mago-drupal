<?php

/**
 * @file
 * Form errors and messenger messages that users see.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Form\FormState;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\SubformState;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Sets form errors and adds messages.
 */
final class UntranslatedMessages {

  use MessengerTrait;

  public function __construct(
    protected MessengerInterface $messages,
  ) {}

  /**
   * A literal error message.
   */
  public function formError(FormStateInterface $form_state): void {
    // @mago-expect analysis:drupal/untranslated-message(1)
    $form_state->setErrorByName('name', 'Enter a name.');
  }

  /**
   * A message that starts with a literal.
   */
  public function concatenated(FormStateInterface $form_state, string $name): void {
    // @mago-expect analysis:drupal/untranslated-message(1)
    $form_state->setErrorByName('name', 'Invalid name: ' . $name);
  }

  /**
   * An error on an element.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array<string, mixed> $element
   *   The element.
   */
  public function elementError(FormStateInterface $form_state, array $element): void {
    // @mago-expect analysis:drupal/untranslated-message(1)
    $form_state->setError($element, 'Enter a value.');
  }

  /**
   * Named arguments, in another order.
   */
  public function named(FormStateInterface $form_state): void {
    // @mago-expect analysis:drupal/untranslated-message(1)
    $form_state->setErrorByName(message: 'Enter a name.', name: 'name');
  }

  /**
   * A concrete form state.
   */
  public function concrete(): void {
    $form_state = new FormState();
    // @mago-expect analysis:drupal/untranslated-message(1)
    $form_state->setErrorByName('name', 'Enter a name.');
  }

  /**
   * A subform state, which declares its own setErrorByName().
   *
   * @param array<string, mixed> $form
   *   The form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public function subform(array $form, FormStateInterface $form_state): void {
    $subform = [];
    $subform_state = SubformState::createForSubform($subform, $form, $form_state);
    // @mago-expect analysis:drupal/untranslated-message(1)
    $subform_state->setErrorByName('name', 'Enter a name.');
  }

  /**
   * A status message through MessengerTrait.
   */
  public function status(): void {
    // @mago-expect analysis:drupal/untranslated-message(1)
    $this->messenger()->addStatus('Saved.');
  }

  /**
   * A warning through an injected messenger.
   */
  public function warning(): void {
    // @mago-expect analysis:drupal/untranslated-message(1)
    $this->messages->addWarning('Check the settings.');
  }

  /**
   * An error message.
   */
  public function error(): void {
    // @mago-expect analysis:drupal/untranslated-message(1)
    $this->messages->addError('Saving failed.');
  }

  /**
   * A message with its type.
   */
  public function message(): void {
    // @mago-expect analysis:drupal/untranslated-message(1)
    $this->messages->addMessage('Saved.', MessengerInterface::TYPE_STATUS);
  }

  /**
   * Translated, empty, markup and computed messages are fine.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $name
   *   The name.
   * @param string $message
   *   A message from elsewhere.
   */
  public function fine(FormStateInterface $form_state, string $name, string $message): void {
    $form_state->setErrorByName('name', new TranslatableMarkup('Enter a name.'));
    $form_state->setErrorByName('name', '');
    $form_state->setErrorByName('name');
    $form_state->setErrorByName('name', '<em>' . $name . '</em>');
    $form_state->setErrorByName('name', "Invalid name: {$name}");
    $form_state->setErrorByName('name', $message);
    $this->messenger()->addStatus(new TranslatableMarkup('Saved.'));
    $this->messages->addMessage($message, 'Saved.');
  }

}

/**
 * Has add*() methods of its own, as PhpUnitTestDiscovery has.
 */
final class UntranslatedMessagesDiscovery {

  /**
   * The messages.
   *
   * @var list<string>
   */
  private array $messages = [];

  /**
   * Adds a warning.
   */
  public function addWarning(string $message): void {
    $this->messages[] = $message;
  }

  /**
   * Adds a status message.
   */
  public function addStatus(string $message): void {
    $this->messages[] = $message;
  }

  /**
   * Calls them with literals, which the check leaves alone.
   *
   * @return list<string>
   *   The messages.
   */
  public function discover(): array {
    $this->addWarning('The container changed during discovery.');
    $this->addStatus('Discovery finished.');

    return $this->messages;
  }

}
