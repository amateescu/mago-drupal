<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal;

use amateescu\MagoDrupal\Analyzer\DrupalPlugin;
use amateescu\MagoDrupal\Linter\Rules\WeakHashRule;
use Mago\Sdk\Extension;

/**
 * Constructs the complete extension advertised by each worker process.
 *
 * This is the only registration API consumers need. Options belong here as
 * typed arguments, not as rule lists the caller has to assemble.
 *
 * @api
 */
final class DrupalExtension
{
    private const VERSION = '0.1.0';

    private function __construct() {}

    /**
     * @param bool $core Enables rules that only apply to Drupal core itself.
     */
    public static function create(bool $core = false): Extension
    {
        return new Extension(
            identifier: 'amateescu/mago-drupal',
            name: 'Drupal',
            version: self::VERSION,
            linterRules: [
                new WeakHashRule(),
            ],
            analyzerPlugins: [
                new DrupalPlugin($core),
            ],
        );
    }
}
