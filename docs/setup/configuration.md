# Configuration

This extension covers the parts of Drupal's standard that need to know about Drupal. Mago itself
handles most of the rest, but some of its defaults differ from Drupal. Each of these settings is one
line that makes Mago match Drupal:

```toml
# The lowest PHP version that the project supports. Mago does not read it from composer.json.
# Without this line, Mago assumes its newest PHP version, and the formatter writes syntax that older
# versions reject, such as new Foo()->bar().
php-version = "8.3"

[formatter]
# Drupal's style for braces and indentation. Lines wrap at 120 columns. Drupal's standard asks for
# about 80, which phpcs does not check for code.
preset = "drupal"
# Mago reads a YAML file as text outside PHP, and the formatter would trim the end of its lines.
excludes = ["**/*.yml"]

[linter]
# Mago's own Drupal switch. The formatter's drupal preset writes TRUE, FALSE and NULL. This switch
# stops `lowercase-keyword` from reporting those three keywords. The rule still reports every other
# upper-case keyword.
integrations = ["drupal"]
# A config export can hold any text. Mago reads a `<?` in it, as in `<?xml`, as a PHP open tag and
# reports parse errors. The info and routing files are never in these directories.
excludes = [
    "**/config/install/**/*.yml",
    "**/config/optional/**/*.yml",
    "**/config/schema/**/*.yml",
    "**/config/sync/**/*.yml",
]

[linter.rules]
# Report an interface name without the "Interface" suffix.
interface-name = { psr = true }
# Report a trait name without the "Trait" suffix, as Coder 9 does.
trait-name = { psr = true }
# Report a file whose name differs from the class that it declares. Coder skips the test classes
# under a module's tests directory and the helper classes in their files, and so do both rules.
file-name = { enabled = true, exclude = ["**/tests/*/**/*Test.php", "**/tests/*/**/*TestBase.php"] }
single-class-per-file = { exclude = ["**/tests/*/**/*Test.php", "**/tests/*/**/*TestBase.php"] }
# drupal/function-name checks function names the way Coder does. Mago's rule wants snake case, so
# it also reports a private helper such as _mymodule_helper(), which Coder accepts.
function-name = { enabled = false }
# drupal/unused-variable reports a variable that its function never reads, and a variable in a
# closure's `use` list that the closure never reads, as Coder does. no-redundant-variable is a
# different check. It reports a value that no later line reads, and also a catch variable, the value
# of a `$key => $value` foreach and a write through a reference, which Coder allows.
# no-unused-closure-capture repeats the `use` list check.
no-redundant-variable = { enabled = false }
no-unused-closure-capture = { enabled = false }

[analyzer]
# Report a PHP function that is called with the wrong case. That is Drupal's
# Squiz.PHP.LowercasePHPFunctions.
check-name-casing = true
# A YAML file holds no PHP for the analyzer. An api.php file documents hooks with example code, and
# test fixtures are often broken on purpose. phpstan-drupal leaves both out as well. The linter
# still checks them, as phpcs does.
excludes = ["**/*.yml", "**/*.api.php", "**/tests/fixtures/**"]
# For a project that also runs PHPStan: let its @phpstan-ignore comments drop the Mago issues that
# report the same finding. See the PHPStan ignores page of the analyzer docs.
# plugins = ["phpstan-ignores"]
```

## Turn off what Drupal's standard doesn't ask for

Mago has many more rules than Drupal's standard. A module that passes phpcs starts out with issues
not asked for by the standard, from rules such as `assert-description` or `no-else-clause`. Turning
those rules off loses no check done by phpcs:

```toml
[linter.rules]
# These rules contradict Drupal's standard, or repeat a rule of this extension.
# Drupal writes a deprecation as @trigger_error(), and drupal/unsilenced-deprecation reports a
# missing @.
# drupal/inline-comment already reports a "#" comment. drupal/todo-comment already checks the
# "@todo Fix problem X here." format, and tagged-todo contradicts that format.
# Drupal's standard allows a global variable named _mymodule_name, and core keeps its settings in
# globals such as $base_url and $GLOBALS['config']. no-global reports every use, at error level.
no-error-control-operator = { enabled = false }
no-global = { enabled = false }
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
# Drupal core declares strict types in its tests, where its phpcs.xml.dist requires them, and in few
# other files. Many contrib modules declare them everywhere. Examine your own codebase before you
# disable this rule.
strict-types = { enabled = false }
```
