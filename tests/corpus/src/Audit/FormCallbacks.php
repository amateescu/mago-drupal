<?php

/**
 * @file
 * Form API callbacks that run and ones that cannot.
 */

declare(strict_types=1);

namespace Drupal\corpus\Audit;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Handlers a form class uses without declaring them.
 */
trait CallbackFormHandlers {

  /**
   * Written in a trait, so the form object is whichever class uses it.
   */
  public function traitElement(array $form): array {
    $form['#submit'] = ['::notInTheTrait'];

    return $form;
  }

  /**
   * Comes from the trait.
   */
  public function submitFromTrait(array &$form, FormStateInterface $form_state): void {
  }

}

/**
 * A form base whose subclasses declare some handlers.
 */
abstract class CallbackBaseForm implements FormInterface {

  /**
   * Comes from the parent.
   */
  public function submitFromParent(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Names a handler only the subclass declares.
   */
  public function baseActions(): array {
    return ['#submit' => ['::childSubmit']];
  }

}

/**
 * Builds a form with good and bad handlers.
 */
final class CallbackForm extends CallbackBaseForm {

  use CallbackFormHandlers;

  /**
   * Handlers that exist: its own, the parent's, the trait's, a helper's.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $handlers = ['::submitForm'];
    $form['submit'] = [
      '#submit' => ['::submitForm', '::submitFromParent', '::submitFromTrait', [CallbackHelper::class, 'submit']],
      '#validate' => $handlers,
      '#ajax' => ['callback' => '::ajaxRefresh', 'wrapper' => 'corpus'],
      '#process' => [[static::class, 'processElement'], [$this, 'ajaxRefresh']],
      '#element_validate' => [static fn (array $element): array => $element, $this->submitForm(...)],
      '#after_build' => [[$this->formObject(), 'whatever']],
      '#entity_builders' => ['corpus.thing:build'],
    ];
    $preview = [];
    $preview['#submit'][] = static::class . '::submitForm';
    $preview['#submit'][] = 'Drupal\corpus\Audit\CallbackHelper::instanceSubmit';
    $form['preview'] = $preview;

    return $form;
  }

  /**
   * A '::method' handler the form does not have.
   */
  public function missingHandler(): array {
    // @mago-expect analysis:drupal/unknown-callback
    return ['#submit' => ['::missingSubmit']];
  }

  /**
   * An appended handler the form does not have.
   */
  public function appendedHandler(): array {
    $form = [];
    // @mago-expect analysis:drupal/unknown-callback
    $form['actions']['submit']['#validate'][] = '::missingValidate';

    return $form;
  }

  /**
   * A handler under a named key.
   */
  public function keyedHandler(): array {
    $form = [];
    // @mago-expect analysis:drupal/unknown-callback
    $form['#entity_builders']['corpus'] = '::missingBuilder';

    return $form;
  }

  /**
   * An #ajax callback set on its own.
   */
  public function ajaxCallback(): array {
    $form = [];
    // @mago-expect analysis:drupal/unknown-callback
    $form['refresh']['#ajax']['callback'] = '::missingAjax';

    return $form;
  }

  /**
   * An #ajax callback inside the #ajax array.
   */
  public function nestedAjaxCallback(): array {
    return [
      // @mago-expect analysis:drupal/unknown-callback
      '#ajax' => ['callback' => [$this, 'missingOwn']],
    ];
  }

  /**
   * A handler added with array_unshift().
   */
  public function unshiftedHandler(): array {
    $form = ['#submit' => ['::submitForm']];
    // @mago-expect analysis:drupal/unknown-callback
    array_unshift($form['#submit'], '::missingFirst');

    return $form;
  }

  /**
   * A handler merged into the existing ones.
   */
  public function mergedHandler(array $submit): array {
    // @mago-expect analysis:drupal/unknown-callback
    return ['#submit' => array_merge($submit, ['::missingMerged'])];
  }

  /**
   * A static method the helper does not have.
   */
  public function missingStatic(): array {
    // @mago-expect analysis:drupal/unknown-callback
    return ['#validate' => ['Drupal\corpus\Audit\CallbackHelper::missing']];
  }

  /**
   * An instance method called statically, which throws on PHP 8.
   */
  public function staticAjax(): array {
    $form = [];
    // @mago-expect analysis:drupal/non-static-callback
    $form['refresh']['#ajax']['callback'] = [static::class, 'ajaxRefresh'];

    return $form;
  }

  /**
   * A value callback is skipped without a word when it cannot be called.
   */
  public function valueCallback(): array {
    // @mago-expect analysis:drupal/non-static-callback
    return ['#value_callback' => [CallbackHelper::class, 'instanceSubmit']];
  }

  /**
   * A value callback does not go through the form object.
   */
  public function formObjectValue(): array {
    // @mago-expect analysis:drupal/unknown-callback
    return ['#value_callback' => '::submitForm'];
  }

  /**
   * A protected value callback, which is_callable() rejects.
   */
  public function protectedValue(): array {
    // @mago-expect analysis:drupal/non-public-callback
    return ['#value_callback' => [$this, 'hiddenSubmit']];
  }

  /**
   * A protected handler of the form.
   */
  public function protectedHandler(): array {
    // @mago-expect analysis:drupal/non-public-callback
    return ['#submit' => ['::hiddenSubmit']];
  }

  /**
   * A handler in parentheses.
   */
  public function parenthesizedHandler(): array {
    // @mago-format-ignore-start
    // The formatter would drop the parentheses.
    // @mago-expect analysis:drupal/unknown-callback
    return ['#submit' => [('::missingParenthesized')]];
    // @mago-format-ignore-end
  }

  /**
   * Machine name checks and file value callbacks that run.
   */
  public function machineName(): array {
    $form = [];
    $form['id'] = [
      '#type' => 'machine_name',
      '#machine_name' => ['exists' => [$this, 'exists'], 'source' => ['label']],
    ];
    $other = [];
    $other['#machine_name']['exists'] = [CallbackHelper::class, 'submit'];
    $form['other'] = $other;
    // Nothing checks whether a machine name callback is trusted.
    $form['file'] = [
      '#machine_name' => ['exists' => [AttributeCallbacks::class, 'plain']],
      '#file_value_callbacks' => [[$this, 'exists'], 'Drupal\corpus\Audit\CallbackHelper::submit'],
    ];

    return $form;
  }

  /**
   * A machine name check the form does not have.
   */
  public function missingExists(): array {
    return [
      // @mago-expect analysis:drupal/unknown-callback
      '#machine_name' => ['exists' => [$this, 'noSuchCheck'], 'source' => ['label']],
    ];
  }

  /**
   * An instance method that call_user_func() calls statically.
   */
  public function staticExists(): array {
    $form = [];
    // @mago-expect analysis:drupal/non-static-callback
    $form['id']['#machine_name']['exists'] = 'Drupal\corpus\Audit\CallbackHelper::instanceSubmit';

    return $form;
  }

  /**
   * A machine name check through the form object.
   */
  public function formObjectExists(): array {
    return [
      // @mago-expect analysis:drupal/unknown-callback
      '#machine_name' => ['exists' => '::exists'],
    ];
  }

  /**
   * A protected method as a file value callback.
   */
  public function protectedFileValue(): array {
    return [
      // @mago-expect analysis:drupal/non-public-callback
      '#file_value_callbacks' => [[$this, 'hiddenSubmit']],
    ];
  }

  /**
   * Validates handlers named by the form it alters.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form['#validate'] = ['::fromTheAlteredForm'];
  }

  /**
   * Checks whether a machine name is taken.
   */
  public function exists(string $id): bool {
    return $id === '';
  }

  /**
   * A handler core cannot reach.
   */
  protected function hiddenSubmit(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * The form object as core hands it out, an interface.
   */
  public function formObject(): FormInterface {
    return $this;
  }

  /**
   * Redraws the form.
   */
  public function ajaxRefresh(array $form): array {
    return $form;
  }

  /**
   * Expands an element.
   */
  public static function processElement(array $element): array {
    return $element;
  }

  /**
   * Declared here, named by the parent.
   */
  public function childSubmit(array &$form, FormStateInterface $form_state): void {
  }

}

/**
 * Takes any method call through __call().
 */
final class MagicCallbackForm implements FormInterface {

  /**
   * Handlers __call() answers, a protected one included.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#submit'] = ['::anything', [$this, 'anythingElse'], '::hidden'];

    return $form;
  }

  /**
   * Reached through __call(), since it is protected.
   */
  // @mago-expect analysis:unused-method
  protected function hidden(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Answers every handler.
   */
  public function __call(string $name, array $arguments): mixed {
    return NULL;
  }

}

/**
 * Handlers shared between forms.
 */
final class CallbackHelper {

  /**
   * A static handler.
   */
  public static function submit(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * An instance handler.
   *
   * The callable resolver instantiates the class when a string names it.
   */
  public function instanceSubmit(array &$form, FormStateInterface $form_state): void {
  }

  /**
   * Not a form, so the form object is some other class.
   */
  public function helperElement(): array {
    return ['#submit' => ['::notHere']];
  }

}

/**
 * Alters forms of other classes.
 */
final class CallbackAlterHooks {

  /**
   * The form object is the altered form, not this class.
   */
  #[Hook('form_alter')]
  public function formAlter(array &$form, FormStateInterface $form_state, string $form_id): void {
    $form['#validate'] = ['::fromTheAlteredForm'];
  }

}
