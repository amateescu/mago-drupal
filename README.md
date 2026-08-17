# mago-drupal

A [Mago](https://github.com/carthage-software/mago) extension that contributes Drupal-specific
knowledge to the linter and analyzer.

> **Status: pre-release.** Mago's extension API ships in 1.47, which is not released yet. The PHP
> SDK is already on Mago's `main` branch, so the rules and tests here build and run against
> `dev-main`. Anything that starts a real worker, including the corpus test, needs a Mago binary
> with `extension-hosts` support: wait for 1.47 or build Mago from source.

## Install

```shell
composer require --dev carthage-software/mago amateescu/mago-drupal
```

Create a worker entrypoint at `.mago/extensions.php`:

```php
<?php

declare(strict_types=1);

use amateescu\MagoDrupal\DrupalExtension;
use Mago\Sdk\Worker;

require dirname(__DIR__) . '/vendor/autoload.php';

(new Worker(DrupalExtension::create()))->run();
```

Register it in `mago.toml`:

```toml
[extension-hosts.drupal]
command = ["php", ".mago/extensions.php"]
```

Pass `DrupalExtension::create(core: true)` when analysing Drupal core itself, which enables rules
that only apply to core.

## What it provides

### Linter rules

| Code | Level | What it reports |
| --- | --- | --- |
| `drupal/weak-hash` | Warning | `md5()`, `sha1()` and `crc32()` calls, and `hash()` called with `md5`, `sha1`, `crc32` or `crc32b`. Carries an unsafe fix to `hash('xxh64', …)`. |

Rule codes are stable. They get written into baselines and `// @mago-expect lint:<code>` comments in
projects we don't control, so they will not be vendor-prefixed or renamed.

### Analyzer plugin

The `drupal` plugin is registered but does not contribute providers yet. Everything it needs
depends on an index built from `*.services.yml`, `*.routing.yml`, plugin attributes and
`*.schema.yml`, so that index comes first. See the `@todo` list in `src/Analyzer/DrupalPlugin.php`.

## Development

```shell
composer install
just check
```

`just check` runs `validate`, `format-check`, `test`, `lint` and `analyze`. `just check-all` adds
`test-corpus`, which starts a real worker and checks the inline `@mago-expect` annotations in
`tests/corpus/src/`.

Mago's Composer binary downloader only resolves tagged releases, so it cannot fetch one for
`dev-main`. Point the recipes at a binary you already have:

```shell
MAGO=/path/to/mago just check
```

## License

MIT.
