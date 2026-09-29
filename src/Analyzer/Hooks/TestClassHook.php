<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Analyzer\Checks\TestClassCheck;
use amateescu\MagoDrupal\Internal\DeclaredClass;
use Mago\Sdk\Analyzer\ClassLikeAnalysisHook;
use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type\Visibility;
use Mago\Sdk\Syntax\NodeKind;

use function str_ends_with;

/**
 * Test class conventions on every PHPUnit TestCase descendant.
 *
 * Test classes are the largest group of classes in core, so the class text,
 * or for the component test rule the file's namespace, decides first whether
 * any rule could report, and only then is the class looked up. The `$modules`
 * property is fetched by name rather than through the class's property list.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class TestClassHook implements ClassLikeAnalysisHook
{
    public function getTargets(): array
    {
        return [ClassLikeTarget::descendantsOf(TestClassCheck::ANCESTORS[0])];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if ($context->node->kind !== NodeKind::Class_) {
            return;
        }

        // The component test rule reads the file's namespace, the other two
        // the class text.
        $mayReport = TestClassCheck::mayReport($context->source->getText($context->node->span));
        if (!$mayReport && !TestClassCheck::mayBeComponentTest($context->source->contents)) {
            return;
        }

        $class = DeclaredClass::resolve($context->source, $context->node, $context->codebase);
        if ($class === null || $class->kind !== ClassLikeKind::Class_) {
            return;
        }

        $name = $class->originalName;
        $where = $class->nameLocation ?? $class->location;
        $reporter = new Reporter($context);
        if ($mayReport && !$class->flags->contains(MetadataFlags::ABSTRACT) && !str_ends_with($name, 'Test')) {
            $reporter->error(TestClassCheck::SUFFIX_CODE, Reporter::issue(
                "Test class {$name} does not end in \"Test\".",
                $where,
                'PHPUnit only picks up classes whose file and class name end in Test.',
                'https://www.drupal.org/docs/develop/standards/php/object-oriented-code#naming',
            ));
        }

        $coreBase = TestClassCheck::coreBase($name, $class->directParentClass, $class->parentClasses);
        if ($coreBase !== null) {
            $reporter->error(TestClassCheck::COMPONENT_CODE, Reporter::issue(
                "Component test {$name} extends {$coreBase}.",
                $where,
                'Extend PHPUnit\Framework\TestCase, so the component tests run without Drupal.',
            ));
        }

        // An inherited or trait property has its name in another file or
        // outside this class and is the declaring class's business.
        $modules = $mayReport ? $context->codebase->getProperty($name, '$modules') : null;
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
