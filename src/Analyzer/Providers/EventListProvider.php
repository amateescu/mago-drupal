<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\ArrayItem;
use Mago\Sdk\Analyzer\Type\ArrayKey;
use Mago\Sdk\Analyzer\Type\ArrayKeyKind;
use Mago\Sdk\Analyzer\Type\KeyedArrayType;

/**
 * Types the event lists of core's entity type and field storage subscriber
 * traits.
 *
 * Core documents both as a bare `array`. Each maps an event name to a list
 * of `[method, priority]` pairs, which is what
 * `EventSubscriberInterface::getSubscribedEvents()` returns, so a subscriber
 * can return the list as it is.
 *
 * @internal
 */
final class EventListProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [
            MethodTarget::exact('Drupal\Core\Entity\EntityTypeEventSubscriberTrait', 'getEntityTypeEvents'),
            MethodTarget::exact(
                'Drupal\Core\Field\FieldStorageDefinitionEventSubscriberTrait',
                'getFieldStorageDefinitionEvents',
            ),
        ];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $pair = Type::fromAtomic(
            new KeyedArrayType(
                [
                    new ArrayItem(new ArrayKey(ArrayKeyKind::Integer, 0), false, Type::string()),
                    new ArrayItem(new ArrayKey(ArrayKeyKind::Integer, 1), false, Type::int()),
                ],
                null,
                null,
                nonEmpty: true,
            ),
        );

        return Type::array(Type::string(), Type::list($pair));
    }
}
