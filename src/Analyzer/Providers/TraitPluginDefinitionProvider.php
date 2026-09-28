<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use amateescu\MagoDrupal\Internal\AnalysisMemo;
use amateescu\MagoDrupal\Internal\TraitUsers;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\Analyzer\UndeclaredReturnTypeProvider;

use function count;
use function strtolower;

/**
 * Types `$this->getPluginDefinition()` in a trait as an array when every
 * class using the trait has array definitions.
 *
 * Mago analyzes a trait on its own, so the call is one the trait does not
 * declare, and `TraitCallProvider` gives it the using classes' declared
 * `array|PluginDefinitionInterface`. This provider is registered first and
 * asks `PluginDefinitionProvider` about each class using the trait instead:
 * `BlockPluginTrait` gets an array, `ContextAwarePluginTrait`, which layouts
 * use too, keeps the union.
 *
 * @internal
 */
final class TraitPluginDefinitionProvider implements MethodReturnTypeProvider, UndeclaredReturnTypeProvider
{
    /**
     * Per lowercased trait name, whether its users all have array
     * definitions.
     *
     * @var AnalysisMemo<bool>
     */
    private readonly AnalysisMemo $traits;

    public function __construct()
    {
        $this->traits = new AnalysisMemo();
    }

    public function getTargets(): array
    {
        return [MethodTarget::anyClass('getPluginDefinition')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        $atomics = $context->invocation->receiverType->atomicTypes ?? [];
        if (count($atomics) !== 1 || !$atomics[0] instanceof NamedObjectType) {
            return null;
        }

        $codebase = $context->codebase;
        $trait = $atomics[0]->name;
        $compute = static fn(): bool => self::usersHaveArrays($codebase, $trait);

        return $this->traits->get($codebase, strtolower($trait), $compute)
            ? Type::array(Type::string(), Type::mixed())
            : null;
    }

    /**
     * Whether the name is a trait whose users all have array definitions.
     */
    private static function usersHaveArrays(Codebase $codebase, string $trait): bool
    {
        if ($codebase->getClassLike($trait)?->kind !== ClassLikeKind::Trait) {
            return false;
        }

        $users = TraitUsers::classes($codebase, $trait);
        foreach ($users as $user) {
            if (!PluginDefinitionProvider::hasArrays($codebase, $user)) {
                return false;
            }
        }

        return $users !== [];
    }
}
