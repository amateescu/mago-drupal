<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\Arguments;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

use function strtolower;

/**
 * Narrows an access handler result by its `$return_as_object` argument.
 *
 * Ports phpstan-drupal's EntityAccessControlHandlerReturnTypeExtension.
 * Drupal 11.4 documents the same thing with a conditional return type, so
 * this only matters against older core, where the declared type is the union.
 *
 * @internal
 */
final class EntityAccessProvider implements MethodReturnTypeProvider
{
    private const HANDLER = 'Drupal\Core\Entity\EntityAccessControlHandlerInterface';

    private const ACCESS_RESULT = 'Drupal\Core\Access\AccessResultInterface';

    /**
     * The `$return_as_object` position per method, lowercased as the host
     * reports names.
     */
    private const FLAG = [
        'access' => 3,
        'createaccess' => 3,
        'fieldaccess' => 4,
    ];

    public function getTargets(): array
    {
        $targets = [];
        foreach (['access', 'createAccess', 'fieldAccess'] as $method) {
            $targets[] = MethodTarget::exact(self::HANDLER, $method);
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $invocation = $context->invocation;
        $position = self::FLAG[strtolower($invocation->name)] ?? null;
        if ($position === null) {
            return null;
        }

        return match (Arguments::literalBool($invocation, $position, false, 'return_as_object')) {
            true => Type::namedObject(self::ACCESS_RESULT),
            false => Type::bool(),
            null => null,
        };
    }
}
