<?php

/**
 * Ready-made worker entrypoint. With it, one TOML block adds this extension.
 *
 * Mago has no equivalent of phpstan/extension-installer, so a project must
 * name a command to run. If you point that command here, you do not have to
 * write a PHP file by hand:
 *
 *     [extension-hosts.drupal]
 *     command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php"]
 *
 * Add `--core` to the command when you analyze Drupal core. Add
 * `--root=PATH` when the Drupal document root is not the cwd, `web/`,
 * `docroot/`, `html/`, `public/`, the Composer scaffold's `web-root` or
 * `vendor/drupal` when Composer installed core there as a package. Add
 * `--deprecations=12` to report only the Drupal deprecations removed in
 * Drupal 12 or earlier. Add `--disable=<code>,<code>` to turn rules off.
 * Mago does not take this extension's rule codes under `[linter.rules]`.
 */

declare(strict_types=1);

use amateescu\MagoDrupal\DrupalExtension;
use Mago\Sdk\Worker;

// Mago reads stdout as the protocol stream, and the SDK only takes over
// stray output once the plugins are registered. A PHP warning before that,
// with the CLI default of printing errors, would land in the stream.
// @mago-expect lint:no-ini-set
ini_set('display_errors', value: 'stderr');

// Xdebug, in any mode, makes the worker spend about a quarter more CPU, and
// Mago never runs a worker under a debugger. It cannot be switched off at
// runtime, so the worker starts again once, with the command line read from
// /proc (which keeps any `-d` options) and XDEBUG_MODE=off. Composer and
// PHPStan restart too, but without loading Xdebug at all; here it stays
// loaded with its mode off. MAGO_DRUPAL_ALLOW_XDEBUG=1 keeps Xdebug for
// debugging the extension itself. Without /proc or pcntl, as on macOS, the
// worker runs as it is.
if (
    function_exists('xdebug_info')
    // @mago-expect lint:no-debug-symbols
    && xdebug_info('mode') !== []
    && getenv('MAGO_DRUPAL_ALLOW_XDEBUG') !== '1'
    && getenv('MAGO_DRUPAL_RESTARTED') !== '1'
    && function_exists('pcntl_exec')
    && is_readable('/proc/self/cmdline')
) {
    $command = explode(separator: "\0", string: rtrim(
        (string) file_get_contents('/proc/self/cmdline'),
        characters: "\0",
    ));
    $environment = [...getenv(), 'XDEBUG_MODE' => 'off', 'MAGO_DRUPAL_RESTARTED' => '1'];
    // The same process goes on as the new program, stdin and stdout included,
    // so Mago keeps talking to it. A failed exec returns and runs as it is.
    pcntl_exec(PHP_BINARY, array_slice($command, offset: 1), $environment);
}

(static function (array $arguments): void {
    $cwd = getcwd();
    $candidates = [
        // The package is a dependency: vendor/amateescu/mago-drupal/resources.
        dirname(__DIR__, levels: 3) . '/autoload.php',
        // The package is a symlinked path repository. __DIR__ resolves into
        // the clone, but Mago starts the worker in the consuming project.
        ($cwd === false ? '.' : $cwd) . '/vendor/autoload.php',
        // The worker runs from a clone of this package.
        dirname(__DIR__) . '/vendor/autoload.php',
    ];

    // A project can hold another copy of this package, such as a release in
    // its vendor directory while the command points at a clone. An
    // autoloader counts only when it maps the package's namespace to this
    // copy, since loading a class from the other one cannot be undone.
    $own = realpath(dirname(__DIR__) . '/src');
    $supplies = static function (string $autoloader) use ($own): bool {
        $psr4 = dirname($autoloader) . '/composer/autoload_psr4.php';
        /** @var array<string, list<string>> $map */
        $map = is_file($psr4) ? (require $psr4) : [];

        return in_array($own, array_map(realpath(...), $map['amateescu\\MagoDrupal\\'] ?? []), strict: true);
    };

    foreach ($candidates as $autoloader) {
        if (!is_file($autoloader) || !$supplies($autoloader)) {
            continue;
        }

        require $autoloader;

        // An autoloader can map this package but come from an install
        // without the SDK, such as a copy that skipped dev dependencies.
        if (!class_exists(Worker::class) || !class_exists(DrupalExtension::class)) {
            continue;
        }

        try {
            $extension = DrupalExtension::fromArguments($arguments);
        } catch (InvalidArgumentException $exception) {
            // Mago reads stdout as the protocol stream, so a failure goes to
            // stderr.
            fwrite(STDERR, "mago-drupal: {$exception->getMessage()}\n");
            exit(1);
        }

        (new Worker($extension))->run();

        return;
    }

    // Mago reads stdout as the protocol stream, so a failure goes to stderr.
    $message = "mago-drupal: could not locate the Composer autoloader.\n";
    fwrite(STDERR, $message);
    exit(1);
})(array_slice($argv, offset: 1));
