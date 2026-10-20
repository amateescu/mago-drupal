# Procedural files

Rules for the procedural files of a module. They take the module name from the file name, the part
before the first dot. All of them report only in `.module` and `.install` files, except
`drupal/global-variable`, which checks every file.

---

## drupal/const-prefix

- **Level:** warning
- **Fix:** none
- **Standard:** [Constants](https://project.pages.drupalcode.org/coding_standards/php/coding/#constants)
- **Ports:** `Drupal.Semantics.ConstantName.ConstConstantStart`

> Module-defined constant names should also be prefixed by an uppercase spelling of the module that
> defines them.

**Reports:**

- a `const` constant at the top level of the file, or after `namespace Foo;`, whose name doesn't
  start with the upper-case module name and an underscore. When one statement declares several
  constants, the rule checks the first.

**Not reported:**

- a `const` inside a braced `namespace Foo { }`. That skip doesn't apply to `define()`.

It checks the prefix the same way as [`drupal/constant-prefix`](#drupalconstant-prefix).

??? example "Example"

    ```php
    // Reported.
    const LIMIT = 10;

    // Fine.
    const EXAMPLE_LIMIT = 10;
    ```

??? note "Compared with Coder"

    - The rule needs the underscore after the module name. Coder 9 only tests that the name starts
      with the upper-case module name, so for a module named `mymod` it accepts `MYMODULE_X`.

---

## drupal/constant-prefix

- **Level:** warning
- **Fix:** none
- **Standard:** [Constants](https://project.pages.drupalcode.org/coding_standards/php/coding/#constants)
- **Ports:** `Drupal.Semantics.ConstantName.ConstantStart`

> Module-defined constant names should also be prefixed by an uppercase spelling of the module that
> defines them.

**Reports:**

- a `define()` constant whose name doesn't start with the upper-case module name and an underscore.
  For a name built with `.`, the rule checks the first string literal.

**Not reported:**

- a name that starts with anything other than a string literal, such as a variable.

The constants of every module share one global namespace, and the prefix keeps them apart.

??? example "Example"

    ```php
    // Reported.
    define('LIMIT', 10);

    // Fine.
    define('EXAMPLE_LIMIT', 10);
    ```

??? note "Compared with Coder"

    - The rule needs the underscore after the module name, as `drupal/const-prefix` does.
    - The rule checks a `define()` call at any depth: in a function, a method, a closure, an `if` or
      a braced `namespace Foo { }`. Coder checks only the calls at the top level of the file. A
      constant from `define()` is global wherever the call is.
    - The rule also reports `\define()`, and a `define()` with a comment before its first argument.
      Coder skips both.
    - In a name built with `.`, a first literal that is the start of the prefix, as in
      `'MY' . 'MODULE_X'` in `mymodule.module`, isn't reported, because the rest of the name can
      complete the prefix. Coder reports it.

---

## drupal/empty-install-hook

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.EmptyInstall.EmptyInstall`

Coder asks not to keep an empty `hook_install()` or `hook_uninstall()`.

**Reports:**

- an empty `hook_install()` or `hook_uninstall()` body in an `.install` file.

---

## drupal/function-prefix

- **Level:** error
- **Fix:** none
- **Standard:** [Functions and variables](https://project.pages.drupalcode.org/coding_standards/php/coding/#functions-and-variables)
- **Ports:** `Drupal.NamingConventions.ValidFunctionName.InvalidPrefix`

> Functions should in addition have the grouping/module name as a prefix, to avoid name collisions
> between modules.

**Reports:**

- a function in a `.module` file whose name doesn't start with the module name and an underscore,
  with an optional leading underscore.

**Not reported:**

- a name that starts with `template_preprocess` or `theme`;
- a function in an `.install` file;
- a function declared inside a class, trait, enum or anonymous class, such as in a method body.

??? example "Example"

    ```php
    // Reported.
    function build_list(): array {
      return [];
    }

    // Fine.
    function example_build_list(): array {
      return [];
    }
    ```

---

## drupal/global-variable

- **Level:** error
- **Fix:** none
- **Standard:** [Global variables](https://project.pages.drupalcode.org/coding_standards/php/coding/#global-variables)
- **Ports:** `Drupal.NamingConventions.ValidGlobal.GlobalUnderScore`

> If you need to define global variables, their name should start with a single underscore followed
> by the module/theme name and another underscore.

**Reports:**

- a variable in a `global` statement that doesn't start with an underscore. Only the first
  underscore is checked, not the module name after it.

**Not reported:**

- the globals owned by Drupal core, such as `$base_url` and `$user`.

Unlike the other rules on this page, the rule checks every file read by Mago, such as `.inc` files
and class methods in `.php` files.

---

## drupal/install-hook-location

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.InstallHooks.InstallHook`

Coder asks for the install hooks to be in the module's `.install` file.

**Reports:**

- an implementation of `hook_install()`, `hook_uninstall()`, `hook_requirements()`, `hook_schema()`,
  `hook_enable()` or `hook_disable()` in a `.module` file.

---

## drupal/t-in-hook-schema

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.TInHookSchema.TFound`

Coder asks for no `t()` in `hook_schema()`. A schema description is developer documentation, so a
translation only adds work for translators.

**Reports:**

- a `t()` call inside `hook_schema()` in an `.install` file.

??? example "Example"

    ```php title="example.install"
    // Reported.
    function example_schema() {
      $schema['example'] = [
        'description' => t('Stores the examples.'),
        'fields' => [],
      ];
      return $schema;
    }

    // Fine.
    function example_schema() {
      $schema['example'] = [
        'description' => 'Stores the examples.',
        'fields' => [],
      ];
      return $schema;
    }
    ```
