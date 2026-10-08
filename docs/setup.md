# Setup

This page covers the install, the worker's arguments, the Mago configuration that matches Drupal's
standard, and the fix levels.

## Install

```shell
composer require --dev carthage-software/mago amateescu/mago-drupal
```

Then register the package's worker in `mago.toml`, and add Drupal's file extensions:

```toml
[source]
extensions = ["php", "module", "install", "inc", "theme", "profile", "engine", "yml"]

[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php"]
```

`yml` lets the rules for [info and routing files](rules/info-and-routing-files.md) read
`.info.yml` and `.routing.yml` files. Mago reads a file without a PHP open tag as text outside PHP,
so the [configuration below](#configure-mago-for-drupal) keeps the formatter and the analyzer out of
YAML files.

Coder also checks `.test` files, a Drupal 7 format. To check them too, add `test` to the
extensions.

The same worker adds the [analyzer plugins](analyzer/index.md) to `mago analyze`. They are on by
default and need no configuration.

Run Mago as `vendor/bin/mago`. The worker loads the PHP SDK from the same Composer package, and the
analyzer plugins need the SDK and the Mago binary to speak the same protocol version. A Mago
installed another way, such as with Homebrew, can speak another version. `mago analyze` then stops
with `Unsupported analyzer protocol version`. The linter rules work either way.

## Worker arguments

The worker takes its arguments from the `command` of its extension host. Add each one as another
string after the path of `worker.php`.

### Checking Drupal core

Add `"--core"` to the command when you check Drupal core itself. It turns off the rules whose checks
core's `phpcs.xml.dist` turns off or does not run:

- `case-fall-through`, `const-prefix`, `function-name`, `function-prefix`, `hook-attribute-name`,
  `method-name-underscore` and `short-list`;
- `inline-comment-blank-line`, `inline-comment-punctuation` and `long-description-punctuation`;
- `author-tag`, `insecure-unserialize` and the [DrupalPractice rules](rules/drupal-practice.md)
  `class-prefix`, `curl-ssl-verify`, `form-alter-comment`, `global-constant`,
  `request-superglobal`, `strict-config-schema` and `untranslated-options`, which all port
  DrupalPractice sniffs;
- the rules for info and routing files that core does not run: `info-core-version-requirement`,
  `info-dependencies-array`, `info-description` and `routing-access`.

It also turns off the two analyzer checks that do not apply to core,
[`drupal/internal-class-extension`](analyzer/hooks.md#drupalinternal-class-extension) and
[`drupal/list-builder-cacheability`](analyzer/entities.md#drupallist-builder-cacheability), and
makes [`drupal/unknown-service`](analyzer/services.md#drupalunknown-service) check every service id,
since core looks up only its own services.

### Finding the Drupal root

The analyzer reads services, config schema and hook documentation from the Drupal root. Without
`--root`, the worker looks for `core/lib/Drupal.php` in these directories, in order, and takes the
first one that holds it:

1. the `web-root` of the Composer scaffold;
2. the project directory;
3. `web/`, `docroot/`, `html/` and `public/`, in that order;
4. `vendor/drupal`, where Composer installs core as a plain package in a module's own repository.
   In that layout the worker also reads the project directory and the other packages under
   `vendor/drupal`.

Add `"--root=PATH"` when the root is somewhere else.

Mago starts the worker in the directory of `mago.toml`, and some checks read files from there. When
the config file is kept outside the project, set `working-directory` to the project:

```toml
[extension-hosts.drupal]
command = ["php", "/path/to/mago-drupal/resources/worker.php", "--root=/path/to/drupal"]
working-directory = "/path/to/project"
```

### Deprecation target

Add `"--deprecations=12"` to report only the Drupal deprecations that are removed in Drupal 12 or
earlier. See [Deprecation target](analyzer/deprecations.md#deprecation-target).

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

`--disable` takes only linter rules. To turn off an [analyzer check](analyzer/index.md#checks), use
Mago's own `[analyzer] ignore`, for the whole project or for some paths:

```toml
[analyzer]
ignore = [
    "drupal/global-drupal-call",
    { code = "drupal/entity-storage-injection", in = "src/Legacy/" },
]
```

## Configure Mago for Drupal

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
# Report a file whose name differs from the class that it declares.
file-name = { enabled = true }
# drupal/function-name checks function names the way Coder does. Mago's rule wants snake case, so
# it also reports a private helper such as _mymodule_helper(), which Coder accepts.
function-name = { enabled = false }
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
# A YAML file holds no PHP for the analyzer. An api.php file documents hooks with example code, and
# test fixtures are often broken on purpose. phpstan-drupal leaves both out as well. The linter
# still checks them, as phpcs does.
excludes = ["**/*.yml", "**/*.api.php", "**/tests/fixtures/**"]
# For a project that also runs PHPStan: let its @phpstan-ignore comments drop the Mago issues that
# report the same finding. See the PHPStan ignores page of the analyzer docs.
# plugins = ["phpstan-ignores"]
```

## Turn off what Drupal's standard does not ask for

Mago has many more rules than Drupal's standard. A module that passes phpcs starts out with issues
that the standard does not ask for, from rules such as `assert-description` or `no-else-clause`.
Turning those rules off loses no check that phpcs did:

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

## Fixes

Many rules have a fix, the way phpcbf fixes Coder's sniffs. `mago lint --fix` applies the safe
ones, and `mago fix` applies them until nothing changes. `--dry-run` shows the diff first. Two
flags add riskier fixes:

- `--potentially-unsafe` adds the fixes that turn a comment into a docblock, which the analyzers
  then read. It also adds the fixes that drop or move comment or docblock text, and the
  `deprecated-tag` rewrite of an old deprecation wording.
- `--unsafe` adds the `weak-hash` rewrite, which changes stored digests.

Both flags also apply Mago's own fixes of that level, such as `strict: true` from
`strict-behavior`, which can change what a loose comparison returns. Each rule's entry under
[Linter rules](rules/index.md) says what its fix does.

## Xdebug

When PHP loads Xdebug, the worker starts again once with `XDEBUG_MODE=off`, because Xdebug slows it
down in any mode. `MAGO_DRUPAL_ALLOW_XDEBUG=1` keeps Xdebug on, for example to step through a rule
or an analyzer check. The restart needs Linux and the `pcntl` extension. Elsewhere, set the mode in
the extension host's configuration:

```toml
[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php"]
environment = { XDEBUG_MODE = "off" }
```

## Index cache

The analyzer caches what it reads from disk under the system temporary directory, in
`mago-drupal-<uid>/`. An entry is keyed by the modification times and sizes of its files, so the
next run picks up an edit. `MAGO_DRUPAL_CACHE=/dir` moves the cache, and `MAGO_DRUPAL_CACHE=0` turns
it off.

A watch or editor session keeps some lists until its workers restart: the `@internal` and
`@deprecated` classes, and the classes that use `DependencySerializationTrait` or declare a
`#[TrustedCallback]` method. Restart the session after changing one of those.
