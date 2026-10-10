# Bugs and security

Rules for code that runs input it should not trust, or reads the wrong value.

## drupal/insecure-unserialize

- **Level:** error
- **Fix:** none
- **Ports:** `DrupalPractice.FunctionCalls.InsecureUnserialize.InsecureUnserialize` (partly)
- **Off with `--core`**

An `unserialize()` call that does not limit `allowed_classes`. The payload then decides which
objects PHP builds, and their destructors run.

**Compared with Coder:**

- The rule skips a payload that the same function built with `serialize()`, which cannot hold a
  foreign class: `unserialize(serialize($x))`, and a local variable whose every assignment is a
  `serialize()` call. That is the shape of a serialization test. Coder reports both.
- The rule skips a call that spreads an array into its arguments, as in `unserialize(...$args)`,
  because the spread can hold the options. Coder reports it.
- The rule also reports `\unserialize()`, `UNSERIALIZE()` and a comment before `TRUE`, as in
  `'allowed_classes' => /* safe */ TRUE`. Coder misses all three.

## drupal/preg-security

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.PregSecurity.PregEFlag`

A `preg_*` pattern that uses the `e` modifier. That modifier evaluates the replacement as PHP. The
rule reads a literal pattern, and the first operand of a concatenation, as in `'/a/e' . $flags`.

**Compared with Coder:**

- Coder also reports a concatenation whose first piece only looks like it ends in modifiers. In
  `'/edit' . $x . '/'` the `e` follows the opening delimiter, and in `'/a\/e' . $x . '/'` a
  backslash escapes the delimiter. The rule skips both.
- The rule closes a bracket delimiter with its counterpart, so it reports `'{a}e'`.
- The rule also reports a call with a leading backslash or in other letter case, such as
  `\preg_replace()` or `PREG_REPLACE()`. Coder skips both, though PHP calls the same function.

## drupal/remote-address

- **Level:** error
- **Fix:** none
- **Ports:** `Drupal.Semantics.RemoteAddress.RemoteAddress`

A read of `$_SERVER['REMOTE_ADDR']`. Drupal can run behind a reverse proxy, and such a read ignores
the proxy settings. A write to the key is fine.

**Compared with Coder:** the rule reports every read. Coder misses a read that starts a statement,
a read in a call argument, `isset()`, an array element or an `if` condition, and a read with a space
or a comment inside the brackets.

## drupal/weak-hash

- **Level:** warning
- **Fix:** unsafe. It writes `hash('xxh64', ...)`, which changes the digest.
- **Ports:** the disallowed calls in core's `phpstan.neon.dist`

An `md5()`, `sha1()` or `crc32()` call, and a `hash()` call with `md5`, `sha1`, `crc32` or `crc32b`.
See the [change record](https://www.drupal.org/node/3581605).

The fix needs `--unsafe`, because a stored digest must be migrated and not only computed again. A
`md5()`, `sha1()` or `crc32()` call with a second argument has no fix.
