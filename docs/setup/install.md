# Install

Install Mago and the extension with Composer:

```shell
composer require --dev carthage-software/mago amateescu/mago-drupal
```

Then register the package's worker in `mago.toml`, and add Drupal's file extensions:

```toml
[source]
extensions = ["php", "module", "install", "inc", "theme", "profile", "engine", "yml"]

[extension-hosts.drupal]
command = ["php", "-d", "xdebug.mode=off", "-d", "pcov.enabled=0", "vendor/amateescu/mago-drupal/resources/worker.php"]
```

The two `-d` options keep Xdebug and pcov from slowing the worker down, see
[Xdebug and pcov](#xdebug-and-pcov).

`yml` lets the rules for [info and routing files](../rules/info-and-routing-files.md) read
`.info.yml` and `.routing.yml` files. Mago reads a file without a PHP open tag as text outside PHP,
so the [configuration](configuration.md) keeps the formatter and the analyzer out of YAML files.

Coder also checks `.test` files, a Drupal 7 format. To check them too, add `test` to the
extensions.

The same worker adds the [analyzer plugins](../analyzer/index.md) to `mago analyze`. They are on by
default and need no configuration.

Run Mago as `vendor/bin/mago`. The worker loads the PHP SDK from the same Composer package, and the
analyzer plugins need the SDK and the Mago binary to speak the same protocol version. A Mago
installed another way, such as with Homebrew, can speak another version. `mago analyze` then stops
with `Unsupported analyzer protocol version`. The linter rules work either way.

## Xdebug and pcov

`-d xdebug.mode=off` turns off Xdebug, and `-d pcov.enabled=0` turns off pcov, the code coverage
driver that PHPUnit can use. While either one is on, the worker takes up to twice as long, even
though nothing debugs it or collects coverage. PHP ignores each option when its extension isn't
installed.

An `XDEBUG_MODE` environment variable wins over `xdebug.mode`, so
`XDEBUG_MODE=debug vendor/bin/mago analyze` turns Xdebug back on, for example to step through a
rule or an analyzer check.

## Index cache

The analyzer caches what it reads from disk under the system temporary directory, in
`mago-drupal-<uid>/`. An entry is keyed by the modification times and sizes of its files, so the
next run picks up an edit. `MAGO_DRUPAL_CACHE=/dir` moves the cache, and `MAGO_DRUPAL_CACHE=0` turns
it off.

A watch or editor session keeps some lists until its workers restart: the `@internal` and
`@deprecated` classes, and the classes that use `DependencySerializationTrait` or declare a
`#[TrustedCallback]` method. Restart the session after changing one of those.
