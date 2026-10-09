# Procedural files

These rules report only in `.module` and `.install` files, except `drupal/global-variable`, which
checks every file. They take the module name from the file name, the part before the first dot.

## drupal/const-prefix

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.Semantics.ConstantName.ConstConstantStart`
- **Off with `--core`**

A `const` constant at the top level of the file, or after `namespace Foo;`, whose name does not
start with the upper-case module name and an underscore. The check is the one of
`drupal/constant-prefix`. When one statement declares several constants, the rule checks the first.

The rule skips a `const` inside a braced `namespace Foo { }`, as Coder 9 does. That skip does not
apply to `define()`.

**Compared with Coder:** the rule needs the underscore after the module name. Coder 9 only tests
that the name starts with the upper-case module name, so for a module named `mymod` it accepts
`MYMODULE_X`.

## drupal/constant-prefix

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.Semantics.ConstantName.ConstantStart`

A `define()` constant whose name does not start with the upper-case module name and an underscore.
The constants of every module share one global namespace, and the prefix keeps them apart.

**Compared with Coder:**

- The rule needs the underscore after the module name, as `drupal/const-prefix` does.
- The rule checks a `define()` call at any depth: in a function, a method, a closure, an `if` or a
  braced `namespace Foo { }`. Coder checks only the calls at the top level of the file. A constant
  from `define()` is global wherever the call is.

## drupal/empty-install-hook

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.EmptyInstall.EmptyInstall`

An empty `hook_install()` or `hook_uninstall()` body in an `.install` file.

## drupal/function-prefix

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.NamingConventions.ValidFunctionName.InvalidPrefix`
- **Off with `--core`**

A function in a `.module` file whose name does not start with the module name and an underscore,
with an optional leading underscore. A name that starts with `template_preprocess` or `theme` is
fine. `.install` files are not checked, as in Coder 9.

## drupal/global-variable

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.NamingConventions.ValidGlobal.GlobalUnderScore`

A variable in a `global` statement that does not start with an underscore. The globals of every
module share one namespace, and the underscore marks the ones that a module owns. The globals that
Drupal core owns, such as `$base_url` and `$user`, are fine.

Unlike the other rules on this page, the rule checks every file that Mago reads, such as `.inc`
files and class methods in `.php` files, as Coder 9 does.

## drupal/install-hook-location

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.InstallHooks.InstallHook`

An implementation of `hook_install()`, `hook_uninstall()`, `hook_requirements()`, `hook_schema()`,
`hook_enable()` or `hook_disable()` in a `.module` file. It belongs in the module's `.install`
file.

## drupal/t-in-hook-schema

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.TInHookSchema.TFound`

A `t()` call inside `hook_schema()` in an `.install` file. A schema description is developer
documentation, so a translation only adds work for translators.
