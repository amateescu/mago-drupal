<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Test;

use amateescu\MagoDrupal\Analyzer\Support\Reporter;
use amateescu\MagoDrupal\Internal\Declaration\DeclaredClass;
use amateescu\MagoDrupal\Internal\Drupal\TestClassSuffix;
use Mago\Sdk\Analyzer\ClassLikeAnalysisHook;
use Mago\Sdk\Analyzer\ClassLikeTarget;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Syntax\NodeKind;

/**
 * Test class conventions on every PHPUnit TestCase descendant.
 *
 * Test classes are the largest group of classes in core, so the class text,
 * or for the component test rule the file's namespace, decides first whether
 * any rule could report, and only then is the class looked up. The `Test`
 * suffix is mostly the linter rule `drupal/test-class-suffix`, and the name
 * check here reports the classes that the linter rule doesn't take for
 * tests. The `$modules` property is fetched by name rather than through the
 * class's property list.
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
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if ($context->node->kind !== NodeKind::Class_) {
            return;
        }

        // The component test rule reads the file's namespace, the other
        // rules the class text.
        $source = $context->source;
        $text = $source->getText($context->node->span);
        $mayReport = TestClassCheck::mayReport($text);
        $misnamed = TestClassSuffix::mayBeMisnamed($text, $source->path, $source->contents);
        if (!$mayReport && !$misnamed && !TestClassCheck::mayBeComponentTest($source->contents)) {
            return;
        }

        $class = DeclaredClass::resolve($source, $context->node, $context->codebase);
        if ($class === null || $class->kind !== ClassLikeKind::Class_) {
            return;
        }

        $name = $class->originalName;
        $where = $class->nameLocation ?? $class->location;
        $reporter = new Reporter($context);
        $shortName = $misnamed ? TestClassSuffix::misnamed($class) : null;
        if ($shortName !== null) {
            $reporter->error(TestClassCheck::NAME_CODE, Reporter::issue(
                "Test class {$shortName} must have a name that ends in Test. PHPUnit runs only the files named *Test.php, so this test never runs.",
                $where,
                'Rename the class and its file to end in Test, or make the class abstract if it is a base class.',
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

        $modules = $mayReport ? $context->codebase->getProperty($name, '$modules') : null;
        $location = TestClassCheck::publicModules($modules, $source->path, $context->node->span);
        if ($location === null) {
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
