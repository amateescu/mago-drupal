<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\Type\ObjectProperty;
use Mago\Sdk\Analyzer\Type\ObjectShapeType;

/**
 * Types a claimed queue item as the object core describes.
 *
 * Core documents `claimItem()` as `bool|object` and lists the properties in
 * the description: `data`, `item_id` and `created`. It returns FALSE when
 * nothing is left, never TRUE. The database queue reads `item_id` and
 * `created` as strings, so both allow either; the shape stays open, since
 * queues add their own properties. Core's queues build the item as a
 * `stdClass`, the database one through `fetchObject()` and the memory one
 * with `new`, so the type says so too. Mago takes an object shape alone for
 * something that is never a `stdClass`, and `assertInstanceOf(\stdClass::class,
 * $item)` would then leave nothing of the item.
 *
 * @internal
 */
final class QueueItemProvider implements MethodReturnTypeProvider
{
    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Queue\QueueInterface', 'claimItem')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $id = Type::union(Type::int(), Type::string());
        $shape = new ObjectShapeType([
            new ObjectProperty('data', false, Type::mixed()),
            new ObjectProperty('item_id', false, $id),
            new ObjectProperty('created', false, $id),
        ], sealed: false);
        $item = Type::fromAtomic(new NamedObjectType('stdClass', null, null, false, false, [$shape], false));

        return Type::union($item, Type::false());
    }
}
