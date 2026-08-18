<?php

/**
 * Ready-made worker entrypoint, so adopting this extension is one TOML block.
 *
 * Mago has no equivalent of phpstan/extension-installer, so a project has to
 * name a command to run. Pointing it here avoids hand-writing a PHP file:
 *
 *     [extension-hosts.drupal]
 *     command = ["php", "vendor/amateescu/mago-drupal/resources/worker.php"]
 *
 * Pass `--core` as a second argument when analysing Drupal core itself.
 */

declare(strict_types=1);

use amateescu\MagoDrupal\DrupalExtension;
use Mago\Sdk\Worker;

(static function (array $arguments): void {
    $cwd = getcwd();
    $candidates = [
        // Installed as a dependency: vendor/amateescu/mago-drupal/resources.
        dirname(__DIR__, levels: 3) . '/autoload.php',
        // Symlinked path-repository install: __DIR__ resolves into the clone,
        // but Mago starts workers in the consuming project's directory.
        ($cwd === false ? '.' : $cwd) . '/vendor/autoload.php',
        // Running from a clone of this package.
        dirname(__DIR__) . '/vendor/autoload.php',
    ];

    foreach ($candidates as $autoloader) {
        if (!is_file($autoloader)) {
            continue;
        }

        require $autoloader;

        // A foreign autoload.php can sit at a probed path. Requiring it is
        // harmless, but only an autoloader that provides this package counts.
        if (!class_exists(Worker::class) || !class_exists(DrupalExtension::class)) {
            continue;
        }

        (new Worker(DrupalExtension::create(core: in_array('--core', $arguments, strict: true))))->run();

        return;
    }

    // Mago reads stdout as the protocol stream, so failures go to stderr.
    $message = "mago-drupal: could not locate the Composer autoloader.\n";
    fwrite(STDERR, $message);
    exit(1);
})(array_slice($argv, offset: 1));
