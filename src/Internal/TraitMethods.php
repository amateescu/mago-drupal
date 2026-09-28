<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

/**
 * What a call on a trait's `$this` returns and accepts, from the method's
 * declarations in the classes using the trait.
 *
 * @internal
 */
final class TraitMethods
{
    /**
     * @param non-empty-list<FunctionLikeMetadata> $methods The declarations,
     *   at least one per distinct place the users get the method from.
     */
    public function __construct(
        private readonly array $methods,
    ) {}

    /**
     * The union of the return types.
     *
     * `static` and `$this` mean the class using the trait, so they become the
     * receiver, and a call chained on the result goes through the users again.
     * A generic method's return type names its templates, which only a call on
     * the class itself resolves, so it stays `mixed`.
     */
    public function returnType(Type $receiver): Type
    {
        $types = [];
        foreach ($this->methods as $method) {
            $type = $method->templates === [] ? $method->returnType->type ?? Type::mixed() : Type::mixed();
            $types[] = Types::withReceiver($type, $receiver);
        }

        return Types::union($types) ?? Type::mixed();
    }

    /**
     * Parameters every declaration accepts; see `SharedParameters`.
     *
     * @return list<CallableParameter>
     */
    public function parameters(): array
    {
        return SharedParameters::of($this->methods);
    }
}
