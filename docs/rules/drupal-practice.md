# DrupalPractice checks

These rules port sniffs of Coder's `DrupalPractice` standard that core's `phpcs.xml.dist` does not
run, so the worker's [`--core` argument](../setup.md#checking-drupal-core) turns them off.

The rules read the file name and the syntax tree, and no other file on disk. Coder's `Project`
class also reads the nearest `*.info.yml` or `*.info` file, to find the module name and to skip a
Drupal 7 module. Each rule says where that makes a difference.

## drupal/class-prefix

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.General.ClassName.ClassPrefix`
- **Off with `--core`**

A class or interface in a `.module`, `.install`, `.profile` or `.theme` file whose name does not
start with the module name, with or without the underscores of the name. The rule skips every
declaration from the first `namespace` statement on. Traits, enums and anonymous classes are not
checked.

**Compared with Coder:** the rule takes the module name from the file name, the part before the
first dot, and skips other files. Coder finds the name in the info file, so it checks other files
too, and for `foo.bar.module` it uses `foo.bar` where the rule uses `foo`.

## drupal/curl-ssl-verify

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.FunctionCalls.CurlSslVerifier.SslPeerVerificationDisabled`
- **Off with `--core`**

A `curl_setopt()` call that sets `CURLOPT_SSL_VERIFYPEER` to `FALSE` or `0`. The rule reads the
arguments by position or by name, and skips a call that spreads its arguments.

**Compared with Coder:**

- The rule ignores the case of the function name and of `FALSE`, and accepts a leading backslash,
  a value in parentheses and a zero in any integer base.
- It reads the whole value, so `FALSE ?: TRUE` does not count. A method call does not count,
  `$object?->curl_setopt()` included.
- It skips a call with too few arguments. Coder stops checking the file there.

## drupal/form-alter-comment

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.FunctionDefinitions.FormAlterDoc.Different`
- **Off with `--core`**

A function in a `.module`, `.install`, `.profile` or `.theme` file with a docblock line that starts
with `Implements hook_form_alter().` and a name other than the module name and `_form_alter`. The
docblock must be right above the `function` keyword, with no modifier, attribute or comment between
them.

**Compared with Coder:**

- The rule takes the module name from the file name, as `drupal/class-prefix` does.
- It compares the function name without regard to case, because PHP does.
- It finds the hook line only at the start of a docblock line, so
  `@see Implements hook_form_alter().` is not a hook line.

## drupal/global-constant

- **Level:** warning
- **Fix:** none
- **Ports:**
    - `DrupalPractice.Constants.GlobalConstant.GlobalConstant`
    - `DrupalPractice.Constants.GlobalDefine.GlobalConstant`
- **Off with `--core`**

A `const` statement at the top level of a file, and a `define()` call at the top level of a
`.module` file. A statement in a class, a function, a closure, an arrow function, a block of `if`,
`switch`, `try`, a loop or `declare`, or a braced namespace is not at the top level.

A docblock with a `@deprecated` tag right above the statement exempts it. The tag must be at the
start of a docblock line, and its name is case-sensitive. A one-line docblock with only the tag
counts, and a tag in the middle of a sentence does not.

**Compared with Coder:**

- The rule does not skip a Drupal 7 module. Coder skips a module whose `.info` file names core 7
  or has no core line.
- It treats the body of an `if`, `elseif` or `else` written without braces, and an arrow function,
  as nested. Coder sees no enclosing scope there.
- It also reports `\define()` and `DEFINE()`, which Coder misses. It does not report
  `$object?->define()` or `define(...)`.
- It keeps the exemption of a `@deprecated` docblock that is above an attribute list.

## drupal/request-superglobal

- **Level:** error
- **Fix:** none
- **Ports:** `DrupalPractice.Variables.GetRequestData`: `SuperglobalAccessed`, `SuperglobalAccessedWithVar`
- **Off with `--core`**

A use of `$_GET`, `$_POST`, `$_COOKIE` or `$_FILES`, including a write, a parameter or `global`
name, and a use inside a string. The message names the matching property of the request, and gives
the source text of the key. `$_REQUEST` is left to Mago's `no-request-variable`.

**Compared with Coder:**

- The rule does not skip a Drupal 7 module, as `drupal/global-constant` does not.
- It reports a use inside a double-quoted string or a heredoc, which Coder misses.
- It treats `$_GET /* note */ ['a']` as an access with a key.

## drupal/strict-config-schema

- **Level:** error
- **Fix:** none
- **Ports:** `DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema`
- **Off with `--core`**

A `$strictConfigSchema` property of a test class, where the first of `TRUE`, `FALSE` and `NULL` in
the default is not `TRUE`, or where there is none. A test class has `Test` or `Tests` as a word of
its name.

**Compared with Coder:** for a property of an anonymous class, the rule uses the nearest named class
or trait, and not the outermost scope. It does not read a name like `Testimonial` or `Testable` as
a test class.

## drupal/untranslated-options

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.General.OptionsT.OptionsValue`
- **Off with `--core`**

A plain string label in the `#options` of an element with `#type` `checkboxes`, `radios`, `select`
or `tableselect`. A label counts when it is not a number and has more than three characters.
Labels in nested option groups count.

**Compared with Coder:**

- The rule takes the `#type` from the array that holds the `#options`, in either order and in
  either quote style. Coder takes the first `'#type'` of the statement.
- It reports the last label of an array and a label in an `array()` group, which Coder skips, and
  it does not need a comma after the label. It accepts `Array(`.
- It does not read a `$form['x']['#options'] = [...]` assignment. Coder reads one only when a
  `'#type'` is in the same statement.
