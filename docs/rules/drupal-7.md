# Drupal 7 era

These rules target APIs that Drupal 8 removed, so they do not report on a modern codebase. Core's
`phpcs.xml.dist` still enables the matching sniffs, and Coder 9 still ships them. Without these
rules, a part of the standard is not checked.

---

## drupal/link-text-translatable

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.LStringTranslatable.LArg`

Coder asks for the link text of `l()` to go through `t()`. Users see the link text, so it must be
translatable.

**Reports:**

- link text passed to `l()` without `t()` that starts with a string literal. The rule reads only
  the first operand. That covers the start of a concatenation such as `'Edit ' . $title` and the
  start of a ternary condition.

**Not reported:**

- text that starts with `<`, which is markup.

??? example "Example"

    ```php
    // Reported.
    $link = l('Edit ' . $title, 'node/1/edit');

    // Fine.
    $link = l(t('Edit @title', ['@title' => $title]), 'node/1/edit');
    ```

---

## drupal/t-in-hook-menu

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.TInHookMenu.TFound`

Coder asks for no `t()` call inside `hook_menu()`. Drupal translates a menu title when it renders
the item, so a translation here comes too early.

**Reports:**

- a `t()` call inside `hook_menu()` in a `.module` file.

??? example "Example"

    ```php
    // Reported.
    function example_menu() {
      $items['admin/config/example'] = [
        'title' => t('Example settings'),
        'page callback' => 'drupal_get_form',
      ];
      return $items;
    }

    // Fine.
    function example_menu() {
      $items['admin/config/example'] = [
        'title' => 'Example settings',
        'page callback' => 'drupal_get_form',
      ];
      return $items;
    }
    ```

---

## drupal/watchdog-message

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.FunctionWatchdog`: `Concat`, `WatchdogArgument`, `WatchdogT`

Coder asks `watchdog()` for a message with placeholders, not one that `t()` or a concatenation
builds.

**Reports:**

- a `watchdog()` call with no message argument;
- a message that is wrapped in `t()`;
- a message that is built by concatenation.

??? example "Example"

    ```php
    // Reported.
    watchdog('example', 'Deleted node ' . $nid);
    watchdog('example', t('Deleted node @nid', ['@nid' => $nid]));

    // Fine.
    watchdog('example', 'Deleted node @nid', ['@nid' => $nid]);
    ```

??? note "Compared with Coder"

    - The rule ignores the case of `watchdog()` and `t()`, and accepts a leading backslash on both,
      as PHP does. Coder skips `\watchdog()`, `WATCHDOG()`, `\t()` and `T()`.
