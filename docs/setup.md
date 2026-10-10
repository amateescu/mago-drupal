# Setup

## Install

```shell
composer require --dev carthage-software/mago amateescu/mago-drupal
```

Then register the shipped worker in `mago.toml` and widen the scanned extensions:

```toml
[source]
extensions = ["php", "module", "install", "inc", "theme", "profile", "engine"]

[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php"]
```

Coder also checks `.test` files, a Drupal 7 format. Add `test` to the extensions to check them too.

## Worker arguments

### Checking Drupal core

Add `"--core"` to the command when you check Drupal core itself. It turns off the rules whose checks
core's `phpcs.xml.dist` turns off or does not run:

- `case-fall-through`, `const-prefix`, `function-prefix`, `hook-attribute-name`,
  `method-name-underscore` and `short-list`;
- `inline-comment-blank-line`, `inline-comment-punctuation` and `long-description-punctuation`;
- `author-tag`, `insecure-unserialize` and the [DrupalPractice rules](rules/drupal-practice.md):
  `class-prefix`, `curl-ssl-verify`, `form-alter-comment`, `global-constant`,
  `request-superglobal`, `strict-config-schema` and `untranslated-options`. All of them port
  DrupalPractice sniffs.

### Turning rules off

Mago does not take this extension's rule codes under `[linter.rules]`. To turn rules off, add
`"--disable=<code>,<code>"` to the command:

```toml
[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php", "--disable=drupal/inline-comment-punctuation"]
```

A rule that is turned off still runs when `mago lint --only` names it. The worker stops with an
error when a code names no rule.

A project that turns phpcs sub-codes off in its `phpcs.xml` can turn off the matching rules this
way. [Coming from Coder](coder/index.md) says which rule ports each sub-code.

## Configure Mago for Drupal

This extension covers the parts of Drupal's standard that need to know about Drupal. Mago itself
handles most of the rest, and some of its checks need one line of configuration to match Drupal
and not Mago's defaults:

```toml
# The lowest PHP version that the project supports. Mago does not read it from composer.json.
# Without this line, Mago assumes its newest PHP version, and the formatter writes syntax that older
# versions reject, such as new Foo()->bar().
php-version = "8.3"

[formatter]
# Drupal's style for braces, indentation and line length.
preset = "drupal"

[linter]
# Mago's own Drupal switch. The formatter's drupal preset writes TRUE, FALSE and NULL. This switch
# stops `lowercase-keyword` from reporting those three keywords. The rule still reports every other
# upper-case keyword.
integrations = ["drupal"]

[linter.rules]
# Report an interface name without the "Interface" suffix.
interface-name = { psr = true }
# Report a trait name without the "Trait" suffix, as Coder 9 does.
trait-name = { psr = true }
# Report a file whose name differs from the class that it declares.
file-name = { enabled = true }
# Accept snake_case functions and camelCase methods. With the drupal integration, the rule skips
# the hook documentation in *.api.php files, such as hook_ENTITY_TYPE_insert().
function-name = { either = true }
# Report a variable that is assigned and never read. That is the part of
# DrupalPractice.CodeAnalysis.VariableAnalysis that core enables.
no-redundant-variable = { enabled = true }
# Report a variable in a closure's `use` list that the closure never reads, the other part of that
# check.
no-unused-closure-capture = { enabled = true }

[analyzer]
# Report a PHP function that is called with the wrong case. That is Drupal's
# Squiz.PHP.LowercasePHPFunctions.
check-name-casing = true
```

## Turn off what Drupal's standard does not ask for

Mago has many more rules than Drupal's standard. A module that passes phpcs starts with issues that
the standard does not ask for, from rules such as `assert-description` or `no-else-clause`. When
you disable those rules, you lose no check that phpcs did:

```toml
[linter.rules]
# These rules contradict Drupal's standard, or repeat a rule of this extension.
# Drupal writes a deprecation as @trigger_error(), and drupal/unsilenced-deprecation reports a
# missing @.
# drupal/inline-comment already reports a "#" comment. drupal/todo-comment already checks the
# "@todo Fix problem X here." format, and tagged-todo contradicts that format.
no-error-control-operator = { enabled = false }
no-hash-comment = { enabled = false }
tagged-todo = { enabled = false }

# The no-empty-loop fix deletes a loop whose condition does the work, as in
# while (--$i >= 0 && ...) {}, and Mago marks that fix safe.
no-empty-loop = { enabled = false }

# Advice that Drupal's standard does not ask for. Keep the rules that you want.
assert-description = { enabled = false }
braced-string-interpolation = { enabled = false }
cyclomatic-complexity = { enabled = false }
excessive-nesting = { enabled = false }
excessive-parameter-list = { enabled = false }
halstead = { enabled = false }
identity-comparison = { enabled = false }
kan-defect = { enabled = false }
literal-named-argument = { enabled = false }
no-assign-in-condition = { enabled = false }
no-boolean-flag-parameter = { enabled = false }
no-else-clause = { enabled = false }
no-empty = { enabled = false }
no-empty-catch-clause = { enabled = false }
no-isset = { enabled = false }
no-multi-assignments = { enabled = false }
no-shorthand-ternary = { enabled = false }
prefer-arrow-function = { enabled = false }
prefer-early-continue = { enabled = false }
prefer-first-class-callable = { enabled = false }
prefer-static-closure = { enabled = false }
readable-literal = { enabled = false }
too-many-methods = { enabled = false }
# Drupal core mostly does not declare strict types, but many contrib modules do. Examine your own
# codebase before you disable this rule.
strict-types = { enabled = false }
```

## Fixes

Many rules have a fix, the way phpcbf fixes Coder's sniffs. `mago lint --fix` applies the safe
ones, and `mago fix` applies them until nothing changes. `--dry-run` shows the diff first.

- `--potentially-unsafe` adds the fixes that turn a comment into a docblock, which the analyzers
  then read, the ones that drop or move comment or docblock text, and the `deprecated-tag` rewrite
  of an old deprecation wording.
- `--unsafe` adds the `weak-hash` rewrite, which changes stored digests.

Both flags also apply Mago's own fixes of that level, such as `strict: true` from
`strict-behavior`, which can change what a loose comparison returns. Each rule's entry under
[Rules](rules/index.md) says what its fix does.

## Xdebug

When PHP loads Xdebug, the worker starts again once with `XDEBUG_MODE=off`, because Xdebug slows it
down in any mode. `MAGO_DRUPAL_ALLOW_XDEBUG=1` keeps Xdebug on, for example to step through a rule.
