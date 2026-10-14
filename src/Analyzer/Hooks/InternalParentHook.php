<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Internal\DeclaredClass;
use Mago\Sdk\Analyzer\ClassLikeAnalysisHook;
use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function explode;
use function str_starts_with;
use function strtolower;

/**
 * Reports a class extending an `@internal` class of another module.
 *
 * Ports phpstan-drupal's ClassExtendsInternalClassRule. The host sends the
 * descendants of every class the disk scan found marked internal, so a class
 * with an ordinary parent costs nothing. The parent's metadata confirms the
 * flag. A module may extend its own internal classes, and its tests count
 * as the module: `Drupal\Tests\node\…` owns what `Drupal\node\…` owns.
 * Anonymous classes never reach a class-like hook, so
 * AnonymousInternalParentHook covers them.
 *
 * @internal
 */
final class InternalParentHook implements ClassLikeAnalysisHook
{
    public const CODE = 'internal-class-extension';

    private const DELETE_FORM = 'Drupal\Core\Entity\ContentEntityDeleteForm';

    private const POLICY = 'https://www.drupal.org/about/core/policies/core-change-policies/drupal-8-and-9-backwards-compatibility-and-internal-api#internal';

    /**
     * @param non-empty-list<non-empty-string> $internal Classes marked
     *   `@internal` on disk, each accepted by `ClassLikeTarget::descendantsOf()`.
     */
    public function __construct(
        private readonly array $internal,
    ) {}

    public function getTargets(): array
    {
        $targets = [];
        foreach ($this->internal as $class) {
            $targets[] = ClassLikeTarget::descendantsOf($class);
        }

        return $targets;
    }

    public function getRequirements(): array
    {
        return [];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if ($context->node->kind !== NodeKind::Class_) {
            return;
        }

        $class = DeclaredClass::resolve($context->source, $context->node, $context->codebase);
        $parentName = $class?->directParentClass;
        if ($class === null || $class->kind !== ClassLikeKind::Class_ || $parentName === null) {
            return;
        }

        self::report(
            $context,
            "Class {$class->originalName}",
            self::owner($class->originalName),
            $parentName,
            $class->nameLocation ?? $class->location,
        );
    }

    /**
     * Reports the class when its parent is `@internal` and owned by another
     * module.
     *
     * @param string|null $owner The module owning the class, as owner() names
     *   it, or null when no module owns it.
     */
    public static function report(
        NodeAnalysisContext $context,
        string $subject,
        ?string $owner,
        string $parentName,
        Span|SourceLocation $where,
    ): void {
        $parent = $context->codebase->getClassLike($parentName);
        if ($parent === null || !$parent->flags->contains(MetadataFlags::INTERNAL)) {
            return;
        }

        if ($owner !== null && $owner === self::owner($parent->originalName)) {
            return;
        }

        $help = match (true) {
            strtolower($parent->originalName) === strtolower(self::DELETE_FORM)
                => 'Extend \Drupal\Core\Entity\ContentEntityConfirmFormBase instead. See https://www.drupal.org/node/2491057',
            str_starts_with(strtolower($parent->originalName), 'drupal\core\\')
                => 'Internal core classes can change in any release. Read the backwards compatibility policy: '
                . self::POLICY,
            default => 'Internal classes can change in any release; copy what is needed or ask the module for an API.',
        };
        (new Reporter($context))->warning(self::CODE, Reporter::issue(
            "{$subject} extends @internal class {$parent->originalName}.",
            $where,
            $help,
        ));
    }

    /**
     * The module that owns a Drupal class: `node` for `Drupal\node\…` and for
     * `Drupal\Tests\node\…`, `Core` for `Drupal\Core\…`. Null outside the
     * `Drupal` namespace, which owns nothing and so is always reported.
     */
    public static function owner(string $class): ?string
    {
        $parts = explode(separator: '\\', string: strtolower($class));
        if ($parts[0] !== 'drupal' || count($parts) < 3) {
            return null;
        }

        return $parts[1] === 'tests' && count($parts) > 3 ? $parts[2] : $parts[1];
    }
}
