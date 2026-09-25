<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\Invocation;

/**
 * Reads the plugin id argument of `createInstance()`.
 *
 * @internal
 */
final class PluginIds
{
    /**
     * `FieldTypePluginManager` and `TypedDataManager` rename the parameter.
     */
    private const NAMES = ['plugin_id', 'field_type', 'data_type'];

    private function __construct() {}

    public static function fromInvocation(Invocation $invocation): ?string
    {
        return $invocation->getArgument(0, ...self::NAMES)?->type?->getLiteralString();
    }
}
