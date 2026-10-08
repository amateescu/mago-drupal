<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\Arguments;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Narrows `Url::toString()` by its `$collect_bubbleable_metadata` argument.
 *
 * Core documents the result as `string|GeneratedUrl`, and the argument
 * decides which one comes back. A stub could say the same with a conditional
 * return type, but a stubbed class passes on to its subclasses only the
 * members the stub declares, so it would have to restate all of `Url`.
 *
 * @internal
 */
final class UrlToStringProvider implements MethodReturnTypeProvider
{
    private const URL = 'Drupal\Core\Url';

    private const GENERATED_URL = 'Drupal\Core\GeneratedUrl';

    public function getTargets(): array
    {
        return [MethodTarget::exact(self::URL, 'toString')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return match (Arguments::literalBool($context->invocation, 0, false, 'collect_bubbleable_metadata')) {
            true => Type::namedObject(self::GENERATED_URL),
            false => Type::string(),
            null => null,
        };
    }
}
