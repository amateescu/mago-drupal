# Drupal 7 era

These rules target APIs that Drupal 8 removed, so they do not report on a modern codebase. Core's
`phpcs.xml.dist` still enables the matching sniffs, and Coder 9 still ships them. Without these
rules, a part of the standard is not checked.

## drupal/link-text-translatable

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.LStringTranslatable.LArg`

Literal link text passed to `l()` without `t()`. Users see the link text, so it must be
translatable.

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
