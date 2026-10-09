<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function in_array;
use function ltrim;
use function preg_match;
use function strtolower;

/**
 * Reports a test class that sets `$strictConfigSchema` to anything but TRUE.
 *
 * Ports DrupalPractice.Objects.StrictSchemaDisabled. A test with the check
 * off hides the config that a module forgot to describe in its schema.
 *
 * The class is a test class when `Test` or `Tests` is a word of its name.
 * Coder looks for the letters anywhere in the name, which also takes
 * `Testimonial` and `Testable`.
 */
final class StrictConfigSchemaRule implements Rule
{
    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/strict-config-schema',
            name: 'Strict config schema',
            description: 'Reports a test class that turns off strict config schema checking.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Class_, NodeKind::Trait],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        $this->gate ??= new FileGate(needles: ['$strictConfigSchema']);
        if (!$this->gate->passes($file)) {
            return;
        }

        $name = Nodes::declaredName($file, $context->node);
        if ($name === null || preg_match('/Tests?(?![a-z])/', $name) !== 1) {
            return;
        }

        foreach ($file->getDescendants($context->node, NodeKind::PropertyItem) as $item) {
            // A property of a class inside this one belongs to that class.
            if (Nodes::isNestedInside($file, $item, $context->node, [NodeKind::Class_, NodeKind::Trait])) {
                continue;
            }

            $parts = $file->getChildren($file->getChildren($item)[0] ?? $item);
            $variable = $parts[0] ?? null;
            if ($variable === null || $file->getText($variable) !== '$strictConfigSchema') {
                continue;
            }

            if (array_key_exists(1, $parts) && $this->startsWithTrue($file, $parts[1])) {
                continue;
            }

            $context->report(Issue::new(
                'Do not disable strict config schema checking in tests. Instead ensure your module properly declares its schema for configurations.',
                $variable->span,
            ));
        }
    }

    /**
     * Whether the first of `TRUE`, `FALSE` and `NULL` in the default value is
     * `TRUE`. Coder reads the value this way, so `TRUE && FALSE` passes and
     * a value with none of the three, such as `[]` or `0`, does not.
     */
    private function startsWithTrue(SourceFile $file, Node $default): bool
    {
        foreach ($file->getDescendants($default) as $node) {
            if ($node->kind !== NodeKind::Keyword && $node->kind !== NodeKind::ConstantAccess) {
                continue;
            }

            $text = strtolower(ltrim($file->getText($node), characters: '\\'));
            if (in_array($text, ['true', 'false', 'null'], strict: true)) {
                return $text === 'true';
            }
        }

        return false;
    }
}
