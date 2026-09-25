<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Analyzer\Checks\TestClassCheck;
use amateescu\MagoDrupal\Internal\DeclaredClass;
use Mago\Sdk\Analyzer\ClassLikeAnalysisHook;
use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type\Visibility;
use Mago\Sdk\Syntax\NodeKind;

use function str_ends_with;

/**
 * Test class conventions on every PHPUnit TestCase descendant.
 *
 * Test classes are the largest group of classes in core, so this costs one
 * class lookup and one property lookup per class: the `$modules` property is
 * fetched by name rather than through the class's property list.
 *
 * @internal
 */
final class TestClassHook implements ClassLikeAnalysisHook
{
    public function getTargets(): array
    {
        return [ClassLikeTarget::descendantsOf(TestClassCheck::ANCESTORS[0])];
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
        if ($class === null || $class->kind !== ClassLikeKind::Class_) {
            return;
        }

        $name = $class->originalName;
        $reporter = new Reporter($context);
        if (!$class->flags->contains(MetadataFlags::ABSTRACT) && !str_ends_with($name, 'Test')) {
            $reporter->error(TestClassCheck::SUFFIX_CODE, Reporter::issue(
                "Test class {$name} does not end in \"Test\".",
                $class->nameLocation ?? $class->location,
                'PHPUnit only picks up classes whose file and class name end in Test.',
                'https://www.drupal.org/docs/develop/standards/php/object-oriented-code#naming',
            ));
        }

        // An inherited or trait property has its name in another file or
        // outside this class and is the declaring class's business.
        $modules = $context->codebase->getProperty($name, '$modules');
        if ($modules === null || $modules->readVisibility !== Visibility::Public) {
            return;
        }

        $location = $modules->nameLocation ?? $modules->location;
        if (
            $location === null
            || $location->file !== $context->source->path
            || !$context->node->span->contains($location->span)
        ) {
            return;
        }

        $reporter->error(TestClassCheck::MODULES_CODE, Reporter::issue(
            "{$name}::\$modules must be protected.",
            $location,
            null,
            'https://www.drupal.org/node/2909426',
        ));
    }
}
