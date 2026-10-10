# Drupal 7 era

These rules target APIs that Drupal 8 removed, so they do not report on a modern codebase. Core's
`phpcs.xml.dist` still enables the matching sniffs, and Coder 9 still ships them. Without these
rules, a part of the standard is not checked.

## drupal/link-text-translatable

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.LStringTranslatable.LArg`

Link text passed to `l()` without `t()` that starts with a string literal. Users see the link text,
so it must be translatable. As in Coder, the rule reads only the first operand. That covers the
start of a concatenation such as `'Edit ' . $title` and the start of a ternary condition. Text that
starts with `<` is markup and does not count.

## drupal/t-in-hook-menu

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.TInHookMenu.TFound`

A `t()` call inside `hook_menu()` in a `.module` file. Drupal translates a menu title when it
renders the item, so a translation here comes too early.

## drupal/watchdog-message

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.FunctionWatchdog`: `Concat`, `WatchdogArgument`, `WatchdogT`

A `watchdog()` call with no message argument, or with a message that is wrapped in `t()` or built
by concatenation. Use placeholders.

**Compared with Coder:** the rule ignores the case of `watchdog()` and `t()`, and accepts a leading
backslash on both, as PHP does. Coder skips `\watchdog()`, `WATCHDOG()`, `\t()` and `T()`.
