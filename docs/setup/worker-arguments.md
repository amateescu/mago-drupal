# Worker arguments

The worker takes its arguments from the `command` of its extension host. Add each one as another
string after the path of `worker.php`.

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
command = ["php", "-d", "xdebug.mode=off", "-d", "pcov.enabled=0", "/path/to/mago-drupal/resources/worker.php", "--root=/path/to/drupal"]
working-directory = "/path/to/project"
```

## Deprecation target

Add `"--deprecations=12"` to report only the Drupal deprecations that are removed in Drupal 12 or
earlier. See [Deprecation target](../analyzer/deprecations.md#deprecation-target).

## Your own worker file

`worker.php` turns its arguments into a call to `DrupalExtension::create()`. A project can make that
call in its own worker file instead, and run other Mago extensions in the same worker. They then
share one pool of PHP processes. With `worker.php`, each extension needs its own extension host,
and each host starts its own pool.

Save the file as `.mago/extensions.php` in the project:

```php
<?php

declare(strict_types=1);

use amateescu\MagoDrupal\DrupalExtension;
use Mago\Sdk\Worker;

// Mago reads the worker's stdout as its protocol stream, so PHP errors go to stderr.
ini_set('display_errors', 'stderr');

require dirname(__DIR__) . '/vendor/autoload.php';

(new Worker(
    DrupalExtension::create(),
    // Other extensions go here.
))->run();
```

Then point the command at it:

```toml
[extension-hosts.drupal]
command = ["php", "-d", "xdebug.mode=off", "-d", "pcov.enabled=0", ".mago/extensions.php"]
```

Each worker argument has a named argument of `create()`:

| Worker argument | `create()` argument |
| --- | --- |
| `--root=PATH` | `root: 'PATH'` |
| `--deprecations=12` | `deprecations: 12` |

For example, `DrupalExtension::create(root: '../drupal', deprecations: 12)` does the same as
`"--root=../drupal", "--deprecations=12"`.
