<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Reporting\AnnotationKind;

use function in_array;
use function preg_match;

/**
 * Drops `missing-magic-method` for magic properties an entity interface has
 * at runtime.
 *
 * The plugin types entity fields and `original` as magic properties, and
 * Mago then reports every access on a variable typed as an interface, since
 * an interface declares no `__get()` or `__set()`. Every content entity class
 * extends `ContentEntityBase`, whose magic methods read and write fields and
 * any other name, and `EntityBase` handles `original` for every entity. A
 * concrete class without the magic method keeps the report, and so does any
 * other property on a config entity interface.
 *
 * Mago names the property in the message and the class in the annotation on
 * the object.
 *
 * @internal
 */
final class EntityMagicPropertyFilter implements IssueFilterHook
{
    private const PROPERTY = '/^Access to documented magic property `\$(?<property>\w+)`/';

    private const CLASS_NAME = '/^Class `(?<class>[^`]+)` is missing the `__(?:get|set)` method$/';

    private const FIELDABLE = 'drupal\core\entity\fieldableentityinterface';

    private const ENTITY = 'drupal\core\entity\entityinterface';

    public function getCodes(): array
    {
        return ['missing-magic-method'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $issue = $context->issue;
        $property = [];
        if (preg_match(self::PROPERTY, $issue->message, $property) !== 1) {
            return IssueFilterDecision::Keep;
        }

        foreach ($issue->annotations as $annotation) {
            $class = [];
            if (
                $annotation->kind !== AnnotationKind::Secondary
                || preg_match(self::CLASS_NAME, (string) $annotation->message, $class) !== 1
            ) {
                continue;
            }

            return self::handled($context, $class['class'], $property['property'])
                ? IssueFilterDecision::Remove
                : IssueFilterDecision::Keep;
        }

        return IssueFilterDecision::Keep;
    }

    /**
     * Whether every class behind the interface handles the property.
     */
    private static function handled(IssueFilterContext $context, string $class, string $property): bool
    {
        $metadata = $context->codebase->getClassLike($class);
        if ($metadata === null || $metadata->kind !== ClassLikeKind::Interface) {
            return false;
        }

        $interfaces = [$metadata->name, ...$metadata->parentInterfaces];
        if (in_array(self::FIELDABLE, $interfaces, strict: true)) {
            return true;
        }

        return $property === 'original' && in_array(self::ENTITY, $interfaces, strict: true);
    }
}
