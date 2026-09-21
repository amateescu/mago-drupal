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
 * Pass `--core` as a second argument when you analyze Drupal core.
 */

declare(strict_types=1);

use amateescu\MagoDrupal\DrupalExtension;
use Mago\Sdk\Worker;

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

    foreach ($candidates as $autoloader) {
        if (!is_file($autoloader)) {
            continue;
        }

        require $autoloader;

        // A foreign autoload.php can be at a probed path. A require of it does
        // no harm, but only an autoloader that supplies this package counts.
        if (!class_exists(Worker::class) || !class_exists(DrupalExtension::class)) {
            continue;
        }

        (new Worker(DrupalExtension::create(core: in_array('--core', $arguments, strict: true))))->run();

        return;
    }

    // Mago reads stdout as the protocol stream, so a failure goes to stderr.
    $message = "mago-drupal: could not locate the Composer autoloader.\n";
    fwrite(STDERR, $message);
    exit(1);
})(array_slice($argv, offset: 1));
