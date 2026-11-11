<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Traits;

use amateescu\MagoDrupal\Internal\Type\Templates;
use amateescu\MagoDrupal\Internal\Type\Types;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Invocation;
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
     * A generic method's return type names its templates. A call that binds
     * every one of them with a class name, as `createMock(Foo::class)` does,
     * gets the type with the classes filled in. Any other call to a generic
     * method stays `mixed`, and so does a type that names something
     * unresolved, as Mago types it on a call on the class.
     */
    public function returnType(Type $receiver, Invocation $invocation, Codebase $codebase): Type
    {
        $types = [];
        foreach ($this->methods as $method) {
            $type = $method->returnType->type ?? Type::mixed();
            if ($method->templates !== []) {
                $bound = Templates::bound($method, $invocation, $codebase);
                $type = ($bound === null ? null : Templates::resolve($type, $bound)) ?? Type::mixed();
            }

            if (Types::unresolved($type)) {
                $type = Type::mixed();
            }

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
