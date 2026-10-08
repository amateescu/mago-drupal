<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\CallableSignatureProvider;
use Mago\Sdk\Analyzer\CallableSignatureProviderContext;
use Mago\Sdk\Analyzer\EffectiveCallableSignature;
use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\UndeclaredReturnTypeProvider;

/**
 * Accepts `referencedEntities()` on any field item list.
 *
 * Only an entity reference field's list declares the method, but an entity's
 * magic field property and `get()` give the plain `FieldItemListInterface`,
 * so `$node->field_tags->referencedEntities()` is reported as a missing method
 * although the field is a reference field. As in phpstan-drupal, the call is
 * accepted on any field item list and returns what the reference list
 * declares. On a list of another field type it throws, which the types cannot
 * tell apart.
 *
 * Mago holds back its missing-method report only for a method a callable
 * signature provider may establish, so this is one as well.
 *
 * @internal
 */
final class ReferencedEntitiesProvider implements
    MethodReturnTypeProvider,
    CallableSignatureProvider,
    UndeclaredReturnTypeProvider
{
    private const FIELD_ITEM_LIST = 'Drupal\Core\Field\FieldItemListInterface';

    private const ENTITY = 'Drupal\Core\Entity\EntityInterface';

    public function getTargets(): array
    {
        return [MethodTarget::exact(self::FIELD_ITEM_LIST, 'referencedEntities')];
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return Type::array(Type::union(Type::int(), Type::string()), Type::namedObject(self::ENTITY));
    }

    public function getCallableSignature(CallableSignatureProviderContext $context): ?EffectiveCallableSignature
    {
        return new EffectiveCallableSignature([]);
    }
}
