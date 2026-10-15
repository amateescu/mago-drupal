# Translation

Checks for text that users see but that can't be translated, such as a literal form error or a
status message.

The linter checks the text passed to `t()` and the options of a form element, see
[`drupal/translatable-literal`](../rules/right-api.md#drupaltranslatable-literal) and
[`drupal/untranslated-options`](../rules/drupal-practice.md#drupaluntranslated-options). The
analyzer types the placeholders of `t()`, see [Stub files](core-types.md#stub-files). The check
below needs the type of the object that a method is called on, so it's an analyzer check.

---

## drupal/untranslated-message

- **Level:** warning
- **Source:** Coder's `DrupalPractice` standard
- **Ports:**
    - `DrupalPractice.FunctionCalls.FormErrorT.ErrorMessage` (partly)
    - `DrupalPractice.FunctionCalls.MessageT.ErrorMessage` (partly)
- **Off with `--core`**

Coder reports a literal message passed to the Drupal 7 functions `form_set_error()`, `form_error()`
and `drupal_set_message()`. Users see the message, so it goes through `t()` to be translated. The
form state and the messenger replace those functions on Drupal 8 and later, and the check reports
the same mistake there.

**Reports:**

- a message that starts with a string literal, passed to `setErrorByName()` or `setError()` of
  `FormStateInterface`, or to `addMessage()`, `addStatus()`, `addWarning()` or `addError()` of
  `MessengerInterface`. Examples are `'Enter a name.'` and `'Invalid name: ' . $name`;
- the same call on a class that implements those interfaces, such as `FormState` and
  `SubformState`, and on the messenger from `\Drupal::messenger()` or `MessengerTrait`;
- a message passed by name, as in `setErrorByName(message: 'Enter a name.', name: 'name')`.

**Not reported:**

- an empty message, `''`, which marks an element as wrong without a message to show;
- a message that starts with `<`, which is markup and not a sentence;
- a double-quoted string with a variable in it, such as `"Invalid name: {$name}"`;
- a call on an object whose type isn't known, such as an untyped `$form_state` parameter or a
  messenger kept in an untyped property;
- a method of the same name on another class, such as `PhpUnitTestDiscovery::addWarning()`;
- test code (any file under a `tests` directory) and hook documentation in `*.api.php` files.

??? example "Example"

    ```php
    // Reported.
    use Drupal\Core\Form\FormStateInterface;

    function example_name_validate(array &$element, FormStateInterface $form_state): void {
      if (empty($element['#value'])) {
        $form_state->setError($element, 'Enter a name.');
      }
    }

    // Fine.
    use Drupal\Core\Form\FormStateInterface;

    function example_name_validate(array &$element, FormStateInterface $form_state): void {
      if (empty($element['#value'])) {
        $form_state->setError($element, t('Enter a name.'));
      }
    }
    ```

??? note "Compared with Coder"

    - Coder's sniffs check only the Drupal 7 functions `form_set_error()`, `form_error()` and
      `drupal_set_message()`. They don't exist on Drupal 9 and later, and `mago analyze` reports
      a call to one as `non-existent-function`. The check reports the methods that replace them
      instead, and leaves the functions alone.
    - Coder also reports an empty message and a message that starts with `<`.
    - Coder reads the arguments by position only.
    - Coder reports test code and hook documentation too.
