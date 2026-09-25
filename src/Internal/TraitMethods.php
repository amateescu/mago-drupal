<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\CallableParameter;

use function array_keys;
use function array_map;

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
     * The first declaration's parameters, each typed only when every
     * declaration gives it the same type.
     *
     * @return list<CallableParameter>
     */
    public function parameters(): array
    {
        $first = $this->methods[0];

        return array_map(
            fn(int $position): CallableParameter => new CallableParameter(
                name: $first->parameters[$position]->name,
                type: $this->agreedType($position),
                byReference: $first->parameters[$position]->flags->contains(MetadataFlags::BY_REFERENCE),
                variadic: $first->parameters[$position]->flags->contains(MetadataFlags::VARIADIC),
                hasDefault: $first->parameters[$position]->flags->contains(MetadataFlags::HAS_DEFAULT),
            ),
            array_keys($first->parameters),
        );
    }

    /**
     * The type every declaration gives the parameter, or null when they
     * differ, one is generic or one leaves it untyped.
     */
    private function agreedType(int $position): ?Type
    {
        $agreed = null;
        foreach ($this->methods as $method) {
            $type = $method->templates === [] ? $method->parameters[$position]->type->type ?? null : null;
            if ($type === null || $agreed !== null && $agreed->encode() !== $type->encode()) {
                return null;
            }

            $agreed = $type;
        }

        return $agreed;
    }
}
