<?php

/**
 * @file
 * The arguments getForm() hands on to the form's buildForm().
 */

declare(strict_types=1);

namespace Drupal\corpus\FormArguments {
  use Drupal\Core\Form\FormInterface;
  use Drupal\Core\Form\FormStateInterface;

  /**
   * Takes a node ID and an optional flag after the form state.
   */
  class NodeIdForm implements FormInterface {

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, int $nid = 0, bool $confirm = FALSE) {
      return $form + ['nid' => $nid, 'confirm' => $confirm];
    }

  }

  /**
   * Takes a node ID that the caller must pass.
   *
   * PHP rejects a form method that requires more than the interface does,
   * so this only happens in broken code.
   */
  final class RequiredNodeIdForm implements FormInterface {

    /**
     * {@inheritdoc}
     */
    // @mago-expect analysis:incompatible-parameter-count
    public function buildForm(array $form, FormStateInterface $form_state, int $nid = 0, string $label) {
      return $form + ['nid' => $nid, 'label' => $label];
    }

  }

  /**
   * Inherits the form of its parent.
   */
  final class ChildNodeIdForm extends NodeIdForm {}

  /**
   * Takes nothing after the form state, so it reads any arguments from there.
   */
  final class PlainForm implements FormInterface {

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state) {
      return $form;
    }

  }

  /**
   * Takes any number of names.
   *
   * PHP accepts a variadic added to an interface method, which Mago reports
   * as a missing required parameter.
   */
  final class NamesForm implements FormInterface {

    /**
     * {@inheritdoc}
     */
    // @mago-expect analysis:incompatible-parameter-count
    public function buildForm(array $form, FormStateInterface $form_state, string ...$names) {
      return $form + ['names' => $names];
    }

  }

  /**
   * Shares its short name with a form in another namespace.
   */
  final class TwinForm implements FormInterface {

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, int $nid = 0) {
      return $form + ['nid' => $nid];
    }

  }

  /**
   * Shares its short name with a class that is no form.
   */
  final class LoneForm implements FormInterface {

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, int $nid = 0) {
      return $form + ['nid' => $nid];
    }

  }

  /**
   * Names a parameter the way getForm() names its first one.
   */
  final class FormArgForm implements FormInterface {

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, int $form_arg = 0) {
      return $form + ['form_arg' => $form_arg];
    }

  }
}

namespace Drupal\corpus\FormArguments\Aliased {
  use Drupal\Core\Form\FormBuilderInterface;
  use Drupal\corpus\FormArguments\NamesForm as NodeIdForm;
  use Drupal\corpus\FormArguments\NodeIdForm as NamesForm;

  /**
   * Import aliases do not acquire an unrelated form's parameters.
   */
  final class AliasCalls {

    /**
     * The aliased form takes the arguments, not the class of that short name.
     */
    public function fits(FormBuilderInterface $builder): array {
      return $builder->getForm(NamesForm::class, 42) + $builder->getForm(NodeIdForm::class, 'one', 'two');
    }

  }
}

namespace Drupal\corpus\FormArguments\Plain {
  /**
   * Shares its short name with a form, and is no form itself.
   */
  final class LoneForm {}
}

namespace Drupal\corpus\FormArguments\Other {
  use Drupal\Core\Form\FormInterface;
  use Drupal\Core\Form\FormStateInterface;

  /**
   * Shares its short name with a form in another namespace.
   */
  final class TwinForm implements FormInterface {

    /**
     * {@inheritdoc}
     */
    public function buildForm(array $form, FormStateInterface $form_state, string $label = '') {
      return $form + ['label' => $label];
    }

  }
}

namespace Drupal\corpus {
  use Drupal\Core\Form\FormBuilder;
  use Drupal\Core\Form\FormBuilderInterface;
  use Drupal\corpus\FormArguments\NodeIdForm;
  use Drupal\corpus\FormArguments\Plain\LoneForm;
  use Drupal\corpus\FormArguments\TwinForm;

  /**
   * Builds forms with the arguments their buildForm() takes, and without.
   */
  final class FormArgumentCalls {

    public function __construct(
      private readonly FormBuilderInterface $formBuilder,
      private readonly FormBuilder $concreteBuilder,
    ) {}

    /**
     * The arguments fit.
     */
    public function fits(): array {
      return (
        $this->formBuilder->getForm('Drupal\corpus\FormArguments\NodeIdForm', 1, TRUE)
        + $this->concreteBuilder->getForm('Drupal\corpus\FormArguments\ChildNodeIdForm', confirm: TRUE)
      );
    }

    /**
     * One argument more than the form takes.
     */
    public function tooMany(): array {
      // @mago-expect analysis:too-many-arguments
      return $this->formBuilder->getForm('Drupal\corpus\FormArguments\NodeIdForm', 1, TRUE, 'extra');
    }

    /**
     * A default before a required parameter makes it required too.
     */
    public function tooFew(): array {
      // @mago-expect analysis:too-few-arguments
      return $this->formBuilder->getForm('Drupal\corpus\FormArguments\RequiredNodeIdForm', 1);
    }

    /**
     * The node ID is an integer.
     */
    public function wrongType(): array {
      // @mago-expect analysis:invalid-argument
      return $this->formBuilder->getForm('Drupal\corpus\FormArguments\NodeIdForm', 'one');
    }

    /**
     * A named argument goes to the parameter of that name, inherited here.
     */
    public function named(): array {
      // @mago-expect analysis:invalid-argument
      return $this->concreteBuilder->getForm('Drupal\corpus\FormArguments\ChildNodeIdForm', confirm: 'yes');
    }

    /**
     * A named argument no parameter of the form has.
     */
    public function unknownName(): array {
      // @mago-expect analysis:invalid-named-argument
      return $this->formBuilder->getForm('Drupal\corpus\FormArguments\NodeIdForm', node: 1);
    }

    /**
     * A class name string, through the global form builder.
     */
    public function classString(): array {
      // @mago-expect analysis:too-many-arguments
      return \Drupal::formBuilder()->getForm('\Drupal\corpus\FormArguments\NodeIdForm', 1, TRUE, 'extra');
    }

    /**
     * A form that takes nothing after the form state takes any arguments.
     */
    public function buildInfo(): array {
      return $this->formBuilder->getForm('Drupal\corpus\FormArguments\PlainForm', 'olivero', TRUE);
    }

    /**
     * A fully qualified class constant on the concrete form builder.
     */
    public function qualified(): array {
      // @mago-expect analysis:invalid-argument
      // @mago-expect lint:drupal/fully-qualified-name
      return $this->concreteBuilder->getForm(\Drupal\corpus\FormArguments\NodeIdForm::class, [1]);
    }

    /**
     * Every name after the form state is a string.
     */
    public function variadic(): array {
      // @mago-expect analysis:invalid-argument
      return $this->formBuilder->getForm('Drupal\corpus\FormArguments\NamesForm', 'a', 2);
    }

    /**
     * A relative `::class` is never read, so the call is not checked.
     *
     * The provider cannot see the file's imports, and a form of the same
     * short name lives in another namespace.
     */
    public function sharedWithPlainClass(): array {
      return $this->formBuilder->getForm(LoneForm::class, 'one');
    }

    /**
     * A form parameter named like getForm()'s first one is not checked.
     */
    public function formArgParameter(): array {
      return $this->formBuilder->getForm('Drupal\corpus\FormArguments\FormArgForm', 'one', 'two');
    }

    /**
     * The same for a short name two form classes share.
     */
    public function ambiguous(): array {
      return $this->formBuilder->getForm(TwinForm::class, 'one', 'two');
    }

    /**
     * Calls that keep core's signature.
     *
     * A class the codebase does not have, a class that is not a form, a
     * computed class, unpacked arguments, a double-quoted class name and
     * self::class are not checked.
     *
     * @param class-string<\Drupal\Core\Form\FormInterface> $class
     *   A form class.
     * @param list<mixed> $arguments
     *   The arguments for the form.
     */
    public function unchecked(string $class, array $arguments): array {
      return (
        $this->formBuilder->getForm('Drupal\corpus\Missing\MissingForm', 1)
        + $this->formBuilder->getForm('Drupal\corpus\Nested\Thing', 1)
        + $this->formBuilder->getForm($class, 1, 2, 3)
        + $this->formBuilder->getForm(NodeIdForm::class, ...$arguments)
        + $this->formBuilder->getForm("Drupal\\corpus\\FormArguments\\NodeIdForm", 'one')
        + $this->formBuilder->getForm(self::class, 'one')
      );
    }

  }
}
