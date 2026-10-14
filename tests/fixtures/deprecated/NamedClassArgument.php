<?php

namespace Drupal\fixture;

/**
 * Passes a `class:` named argument before its deprecated method.
 */
interface NamedClassArgument
{
    public function build(Options $options = new Options(class: 1)): void;

    public string $name { get; }

    /**
     * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. Use
     *   build() instead.
     */
    public function legacy(): void;
}
