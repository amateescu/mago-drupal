<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Providers;

use Mago\Sdk\Analyzer\MethodReturnTypeProvider;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\ReturnTypeProviderContext;
use Mago\Sdk\Analyzer\Type;

/**
 * Hands back the receiver for the fluent methods core keeps in a trait.
 *
 * Each of these bodies ends in `return $this`, and the interface that declares
 * the method documents `@return $this`. The trait itself carries only
 * `{@inheritdoc}`, and a trait has no parent to inherit from, so the call
 * reads as `mixed` and takes the rest of the chain with it:
 * `AccessResult::allowed()->addCacheableDependency($entity)->andIf(...)` loses
 * the access result at the first link. The same call through the interface is
 * typed already, and the receiver is what it resolves to.
 *
 * @internal
 */
final class SelfReturnProvider implements MethodReturnTypeProvider
{
    /**
     * Trait name to the methods of it that only ever return `$this`.
     *
     * The host reports the class a method is declared on, which for these is
     * the trait rather than the interface or the class using it.
     */
    private const METHODS = [
        'Drupal\Component\Plugin\Definition\ContextAwarePluginDefinitionTrait' => [
            'addContextDefinition',
            'removeContextDefinition',
        ],
        'Drupal\Core\Access\RefinableDependentAccessTrait' => ['addAccessDependency', 'setAccessDependency'],
        'Drupal\Core\Cache\CacheableResponseTrait' => ['addCacheableDependency'],
        'Drupal\Core\Cache\RefinableCacheableDependencyTrait' => [
            'addCacheContexts',
            'addCacheTags',
            'addCacheableDependency',
            'mergeCacheMaxAge',
        ],
        'Drupal\Core\Database\Query\QueryConditionTrait' => [
            'alwaysFalse',
            'condition',
            'exists',
            'isNotNull',
            'isNull',
            'notExists',
            'where',
        ],
        'Drupal\Core\Entity\EntityPublishedTrait' => ['setPublished', 'setUnpublished'],
        'Drupal\Core\Entity\RevisionLogEntityTrait' => [
            'setRevisionCreationTime',
            'setRevisionLogMessage',
            'setRevisionUser',
            'setRevisionUserId',
        ],
        'Drupal\Core\Entity\SynchronizableEntityTrait' => ['setSyncing'],
        'Drupal\Core\Form\FormStateValuesTrait' => ['setValue', 'setValues', 'unsetValue'],
        'Drupal\Core\Plugin\ContextAwarePluginTrait' => ['setContextMapping', 'setContextValue'],
        'Drupal\Core\Plugin\Definition\DependentPluginDefinitionTrait' => ['setConfigDependencies'],
        'Drupal\Core\Render\AttachmentsTrait' => ['addAttachments', 'setAttachments'],
        'Drupal\Core\TypedData\ComputedItemListTrait' => ['applyDefaultValue'],
        'Drupal\field_layout\Entity\FieldLayoutEntityDisplayTrait' => [
            'calculateDependencies',
            'ensureLayout',
            'setLayout',
            'setLayoutId',
        ],
        'Drupal\layout_builder\SectionListTrait' => [
            'appendSection',
            'insertSection',
            'removeAllSections',
            'removeSection',
        ],
        'Drupal\user\EntityOwnerTrait' => ['setOwner', 'setOwnerId'],
    ];

    public function getTargets(): array
    {
        $targets = [];
        foreach (self::METHODS as $trait => $methods) {
            foreach ($methods as $method) {
                $targets[] = MethodTarget::exact($trait, $method);
            }
        }

        return $targets;
    }

    public function getReturnType(ReturnTypeProviderContext $context): ?Type
    {
        return $context->invocation->receiverType;
    }
}
