<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Nodes;
use amateescu\MagoDrupal\Internal\TestClassSuffix;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

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
 * name doesn't say anything: core's test entity class is called `EntityTest`. A
 * parent declared in the same file without such a parent of its own is a
 * fixture. TestClassSuffix holds these facts, and the analyzer check
 * `drupal/test-class-name` reports the other TestCase descendants. Coder has
 * no such sniff. Drupal's coding standards ask for the suffix.
 */
final class TestClassSuffixRule implements Rule
{
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
        if (!TestClassSuffix::inTestDirectory($file->path)) {
            return;
        }

        foreach ($file->getChildren($class) as $child) {
            if ($child->kind === NodeKind::Modifier && strtolower(trim($file->getText($child))) === 'abstract') {
                return;
            }
        }

        $parent = Nodes::clauseNames($file, $class, NodeKind::Extends)[0] ?? '';
        $identifier = Nodes::declaredIdentifier($file, $class);
        if ($identifier === null || !TestClassSuffix::testParent($file->contents, $parent)) {
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
