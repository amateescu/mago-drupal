<?php

/**
 * @file
 * Forms that return a response from buildForm() instead of the form.
 */

declare(strict_types=1);

namespace Drupal\corpus;

use Drupal\Core\Form\FormInterface;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Redirects when there is nothing to confirm, which the form builder allows.
 */
final class RedirectingForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($form === []) {
      return new RedirectResponse('/');
    }

    return $form;
  }

}

/**
 * Returns a value the form builder does not handle.
 */
final class StringForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($form === []) {
      // @mago-expect analysis:invalid-return-statement
      return 'nothing to confirm';
    }

    return $form;
  }

}

/**
 * Declares a native return type, so returning a response is a TypeError.
 */
final class TypedRedirectingForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    if ($form === []) {
      // @mago-expect analysis:invalid-return-statement
      return new RedirectResponse('/');
    }

    return $form;
  }

}

/**
 * Allows the response in its native return type, which the docblock narrows.
 */
final class UnionRedirectingForm implements FormInterface {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): RedirectResponse|array {
    if ($form === []) {
      return new RedirectResponse('/');
    }

    return $form;
  }

}

/**
 * Has a buildForm() method without being a form.
 */
final class FormLookalike {

  /**
   * Builds the array, or redirects when there is nothing to build.
   *
   * @return array
   *   The built array.
   */
  public function buildForm(bool $empty) {
    if ($empty) {
      // @mago-expect analysis:invalid-return-statement
      return new RedirectResponse('/');
    }

    return [];
  }

}
