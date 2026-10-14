<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\Argument;
use Mago\Sdk\Analyzer\Type;

/**
 * Reads a service id off an analyzed argument.
 *
 * Drupal registers interface aliases under the class name, so `Foo::class` is
 * as much an id as `'foo'` is.
 *
 * @internal
 */
final class ServiceIds
{
    private function __construct() {}

    /**
     * @return non-empty-string|null
     */
    public static function fromArgument(?Argument $argument): ?string
    {
        return self::fromType($argument?->type);
    }

    /**
     * @return non-empty-string|null
     */
    public static function fromType(?Type $type): ?string
    {
        $id = $type?->getLiteralString() ?? $type?->getLiteralClassString();

        return $id === '' ? null : $id;
    }
}
