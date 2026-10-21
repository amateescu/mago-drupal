<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Cache\FileGate;
use amateescu\MagoDrupal\Internal\Syntax\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function strip_tags;
use function strlen;
use function trim;

/**
 * Reports a plain string as the `#description` of a form element or render
 * array, where the text needs `t()`.
 *
 * Ports DrupalPractice.General.DescriptionT. As in Coder, a string counts
 * when it has more than three characters once its tags are gone.
 */
final class UntranslatedDescriptionRule implements Rule
{
    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/untranslated-description',
            name: 'Untranslated description',
            description: 'Reports a plain string as the #description of a form element or render array.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::KeyValueArrayElement, NodeKind::Assignment],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        $this->gate ??= new FileGate(needles: ['#description']);
        if (!$this->gate->passes($file)) {
            return;
        }

        $pair = $context->node->kind === NodeKind::Assignment
            ? self::assignedPair($file, $context->node)
            : self::elementPair($file, $context->node);
        if ($pair === null || Values::literalString($file, $pair[0]) !== '#description') {
            return;
        }

        // Coder reads the first string of a concatenation. An interpolated
        // string isn't a literal string node, so it is left out.
        $text = Values::leftmost($file, $pair[1]);
        if ($text->kind !== NodeKind::LiteralString || strlen(strip_tags($file->getText($text))) <= 5) {
            return;
        }

        $context->report(Issue::new('Pass the #description text through t().', $text->span));
    }

    /**
     * Returns the key and the value of a `key => value` element, unwrapped.
     *
     * @return null|array{Node, Node}
     */
    private static function elementPair(SourceFile $file, Node $element): ?array
    {
        $parts = $file->getChildren($element);
        if (count($parts) < 2) {
            return null;
        }

        return [Values::unwrap($file, $parts[0]), Values::unwrap($file, $parts[1])];
    }

    /**
     * Returns the last index of the array access that a plain `=` assigns to
     * and the assigned value, unwrapped, as in `$form['x']['#description'] =
     * 'Text'`.
     *
     * @return null|array{Node, Node}
     */
    private static function assignedPair(SourceFile $file, Node $assignment): ?array
    {
        $parts = $file->getChildren($assignment);
        if (count($parts) !== 3 || trim($file->getText($parts[1])) !== '=') {
            return null;
        }

        $target = Values::unwrap($file, $parts[0]);
        if ($target->kind !== NodeKind::ArrayAccess) {
            return null;
        }

        $index = $file->getChildren($target)[1] ?? null;

        return $index === null ? null : [Values::unwrap($file, $index), Values::unwrap($file, $parts[2])];
    }
}
