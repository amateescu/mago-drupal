<?php

declare(strict_types=1);

// @mago-expect lint:drupal/class-prefix
/**
 * Takes the module name from the nearest info file, as Coder does.
 *
 * A `.php` file has no module name of its own, and corpus.info.yml gives it
 * one, so its class needs the prefix too.
 */
class ClassPrefixInfoFile {}
