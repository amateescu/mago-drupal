# Worker arguments

The worker takes its arguments from the `command` of its extension host. Add each one as another
string after the path of `worker.php`.

## Checking Drupal core

Add `"--core"` to the command when you check Drupal core itself. It turns off the rules whose checks
are turned off or not run by core's `phpcs.xml.dist`:

- `case-fall-through`, `const-prefix`, `function-name`, `function-prefix`, `hook-attribute-name`,
  `method-name-underscore` and `short-list`;
- `inline-comment-blank-line`, `inline-comment-punctuation` and `long-description-punctuation`;
- `author-tag`, `insecure-unserialize` and the [DrupalPractice rules](../rules/drupal-practice.md)
  `class-prefix`, `curl-ssl-verify`, `form-alter-comment`, `global-constant`,
  `request-superglobal`, `strict-config-schema` and `untranslated-options`, which all port
  DrupalPractice sniffs;
- the rules for info and routing files not run by core: `info-core-version-requirement`,
  `info-dependencies-array`, `info-description` and `routing-access`.

It also turns off the two analyzer checks that don't apply to core,
[`drupal/internal-class-extension`](../analyzer/hooks.md#drupalinternal-class-extension) and
[`drupal/list-builder-cacheability`](../analyzer/entities.md#drupallist-builder-cacheability), and
makes [`drupal/unknown-service`](../analyzer/services.md#drupalunknown-service) check every service
id, since core looks up only its own services.

## Finding the Drupal root

The analyzer reads services, config schema and hook documentation from the Drupal root. Without
`--root`, the worker looks for `core/lib/Drupal.php` in these directories, in order, and takes the
first one that holds it:

1. the `web-root` of the Composer scaffold;
2. the project directory;
3. `web/`, `docroot/`, `html/` and `public/`, in that order;
4. `vendor/drupal`, where core is installed by Composer as a plain package in a module's own
   repository. In that layout the worker also reads the project directory and the other packages
   under `vendor/drupal`.

Add `"--root=PATH"` when the root is somewhere else.

Mago starts the worker in the directory of `mago.toml`, and some checks read files from there. When
the config file is kept outside the project, set `working-directory` to the project:

```toml
[extension-hosts.drupal]
command = ["php", "/path/to/mago-drupal/resources/worker.php", "--root=/path/to/drupal"]
working-directory = "/path/to/project"
```

## Deprecation target

Add `"--deprecations=12"` to report only the Drupal deprecations that are removed in Drupal 12 or
earlier. See [Deprecation target](../analyzer/deprecations.md#deprecation-target).

## Turning rules off

Mago doesn't take this extension's rule codes under `[linter.rules]`. To turn rules off, add
`"--disable=<code>,<code>"` to the command:

```toml
[extension-hosts.drupal]
command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php", "--disable=drupal/inline-comment-punctuation"]
```

A rule that is turned off still runs when `mago lint --only` names it. The worker stops with an
error when a code names no rule.

A project that turns phpcs sub-codes off in its `phpcs.xml` can turn off the matching rules this
way. [Coming from Coder](../coder/index.md) says which rule ports each sub-code.

`--disable` takes only linter rules. To turn off an [analyzer check](../analyzer/index.md#checks),
use Mago's own `[analyzer] ignore`, for the whole project or for some paths:

```toml
[analyzer]
ignore = [
    "drupal/global-drupal-call",
    { code = "drupal/entity-storage-injection", in = "src/Legacy/" },
]
```
