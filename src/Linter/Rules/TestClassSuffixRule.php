<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function str_ends_with;
use function strtolower;
use function trim;

/**
 * Reports a test class whose name does not end in `Test`.
 *
 * PHPUnit runs only the files whose name ends in `Test.php`, and so do
 * `phpunit -c core` and run-tests.sh, so such a test never runs. A test class
 * is a concrete class in a PHPUnit test directory whose parent's name ends in
 * `Test`, `TestBase` or `TestCase`. Outside those directories the parent's
 * name says nothing: core's test entity class is called `EntityTest`. Coder
 * has no such sniff. Drupal's coding standards ask for the suffix.
 */
final class TestClassSuffixRule implements Rule
{
    /**
     * The PHPUnit test directories of a module.
     */
    private const MODULE_TESTS = '#/tests/src/(?:Unit|Kernel|Functional|FunctionalJavascript|Build)/#';

    /**
     * The PHPUnit test directories of core.
     */
    private const CORE_TESTS = '#/core/tests/Drupal/(?:Tests|KernelTests|FunctionalTests|FunctionalJavascriptTests|BuildTests)/#';

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/test-class-suffix',
            name: 'Test class suffix',
            description: "Reports a test class whose name doesn't end in Test, which PHPUnit never runs.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        $class = $context->node;
        if (preg_match(self::MODULE_TESTS, $file->path) !== 1 && preg_match(self::CORE_TESTS, $file->path) !== 1) {
            return;
        }

        foreach ($file->getChildren($class) as $child) {
            if ($child->kind === NodeKind::Modifier && strtolower(trim($file->getText($child))) === 'abstract') {
                return;
            }
        }

        $parent = Nodes::clauseNames($file, $class, NodeKind::Extends)[0] ?? '';
        $identifier = Nodes::declaredIdentifier($file, $class);
        if ($identifier === null || preg_match('/(?:Test|TestBase|TestCase)$/', $parent) !== 1) {
            return;
        }

        $name = $file->getText($identifier);
        if (str_ends_with($name, 'Test')) {
            return;
        }

        $context->report(Issue::new(
            "Test class {$name} must have a name that ends in Test. PHPUnit runs only the files named *Test.php, so this test never runs.",
            $identifier->span,
        ));
    }
}
