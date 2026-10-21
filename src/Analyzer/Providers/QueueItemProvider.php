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

use function in_array;
use function strtolower;

/**
 * Types a claimed queue item as the object core describes.
 *
 * Core documents `claimItem()` as `bool|object` and lists the properties in
 * the description: `data`, `item_id` and `created`. It returns FALSE when
 * the queue is empty, never TRUE.
 *
 * Core's own queues build the item as a `stdClass`, the database one through
 * `fetchObject()` and the memory one with `new`. A call on `QueueInterface`
 * or on one of them returns that `stdClass` with the documented properties.
 * The database queue reads `item_id` and `created` as strings, so both allow
 * either, and the shape stays open, since queues add their own properties.
 * Mago takes an object shape alone for something that is never an instance
 * of any class, so the `stdClass` keeps `instanceof \stdClass` possible.
 *
 * A queue outside core can return an object of its own class, which the
 * `stdClass` on the interface rules out. A queue class that declares its own
 * `claimItem()` keeps what it declares.
 *
 * @internal
 */
final class QueueItemProvider implements MethodReturnTypeProvider
{
    /**
     * The interface and core's queues, lowercased, typed as a `stdClass`.
     */
    private const STDCLASS_QUEUES = [
        'drupal\core\queue\queueinterface',
        'drupal\core\queue\databasequeue',
        'drupal\core\queue\batch',
        'drupal\core\queue\memory',
        'drupal\core\queue\batchmemory',
    ];

    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Queue\QueueInterface', 'claimItem')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $declaring = strtolower($context->invocation->declaringClass ?? '');
        if (!in_array($declaring, self::STDCLASS_QUEUES, strict: true)) {
            return null;
        }

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
