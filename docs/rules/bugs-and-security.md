# Bugs and security

Rules for code that runs input it shouldn't trust, or reads the wrong value.

---

## drupal/insecure-unserialize

- **Level:** error
- **Fix:** none
- **Source:** Coder's `DrupalPractice` standard
- **Ports:** `DrupalPractice.FunctionCalls.InsecureUnserialize.InsecureUnserialize` (partly)

DrupalPractice asks an `unserialize()` call to limit the classes it builds. Without a limit, the
payload decides which objects PHP builds, and their destructors run.

**Reports:**

- an `unserialize()` call that doesn't limit `allowed_classes`. Only a key of the options array
  itself counts, and `(TRUE)` counts as `TRUE`;
- `unserialize(...)`, which passes the call on with no options.

**Not reported:**

- a payload that the same function built with `serialize()`, which can't hold a foreign class:
  `unserialize(serialize($x))`, and a local variable whose every assignment is a `serialize()`
  call. That is the shape of a serialization test;
- a call that spreads an array into its arguments, as in `unserialize(...$args)`, because the
  spread can hold the options.

??? example "Example"

    ```php
    // Reported.
    $data = unserialize($value);

    // Fine.
    $data = unserialize($value, ['allowed_classes' => FALSE]);
    ```

??? note "Compared with Coder"

    - Coder reports a payload built with `serialize()` and a call that spreads its arguments.
    - Coder takes the second argument for the options, so it reports
      `unserialize(options: ['allowed_classes' => FALSE], data: $d)`. The rule reads a named
      argument by its name.
    - The rule also reports `\unserialize()`, `UNSERIALIZE()`, `\unserialize(...)`, `(TRUE)`, a
      comment before `TRUE`, as in `'allowed_classes' => /* safe */ TRUE`, and options whose only
      `allowed_classes` key is in a nested array. Coder misses them.

---

## drupal/preg-security

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.PregSecurity.PregEFlag`

Coder asks for no `e` modifier on a `preg_*` pattern. That modifier evaluates the replacement as
PHP.

**Reports:**

- a `preg_*` pattern that uses the `e` modifier. The rule reads a literal pattern, and the first
  operand of a concatenation, as in `'/a/e' . $flags`.

**Not reported:**

- a concatenation whose first piece only looks like it ends in modifiers. In `'/edit' . $x . '/'`
  the `e` follows the opening delimiter, and in `'/a\/e' . $x . '/'` a backslash escapes the
  delimiter.

The rule closes a bracket delimiter with its counterpart, so it reports `'{a}e'`.

??? example "Example"

    ```php
    // Reported.
    $text = preg_replace('/(\w+)/e', 'strtoupper("$1")', $text);

    // Fine.
    $text = preg_replace_callback('/(\w+)/', fn (array $m): string => strtoupper($m[1]), $text);
    ```

??? note "Compared with Coder"

    - Coder also reports a concatenation whose first piece only looks like it ends in modifiers.
    - Coder doesn't close a bracket delimiter with its counterpart, so it misses `'{a}e'`.
    - The rule also reports a call with a leading backslash or in other letter case, such as
      `\preg_replace()` or `PREG_REPLACE()`. Coder skips both, though PHP calls the same function.

---

## drupal/remote-address

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Semantics.RemoteAddress.RemoteAddress`

Coder asks for the request's client IP in place of `$_SERVER['REMOTE_ADDR']`. Drupal can run
behind a reverse proxy, and a read of the key ignores the proxy settings.

**Reports:**

- a read of `$_SERVER['REMOTE_ADDR']`.

**Not reported:**

- a write to the key.

??? example "Example"

    ```php
    // Reported.
    $ip = $_SERVER['REMOTE_ADDR'];

    // Fine.
    $ip = \Drupal::request()->getClientIp();
    ```

??? note "Compared with Coder"

    - The rule reports every read. Coder misses a read that starts a statement, a read in a call
      argument, `isset()`, an array element or an `if` condition, and a read with a space or a
      comment inside the brackets.

---

## drupal/weak-hash

- **Level:** warning
- **Fix:** unsafe. It writes `hash('xxh64', ...)`, which changes the digest.
- **Source:** core's `phpstan.neon.dist`
- **Ports:** its disallowed calls of `md5()`, `sha1()`, `crc32()` and `hash()`

Core's PHPStan configuration disallows the weak hash functions. See the
[change record](https://www.drupal.org/node/3581605).

**Reports:**

- an `md5()`, `sha1()` or `crc32()` call, and a `hash()` call with `md5`, `sha1`, `crc32` or
  `crc32b`.

The fix needs `--unsafe`, because a stored digest must be migrated and not only computed again. An
`md5()` or `sha1()` call with a second argument doesn't have a fix.
