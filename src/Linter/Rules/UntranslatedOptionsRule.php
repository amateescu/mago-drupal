<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\ArrayElements;
use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function is_numeric;
use function strlen;

/**
 * Reports a plain string in the `#options` of a checkboxes, radios, select
 * or tableselect element, where the label needs `t()`.
 *
 * Ports DrupalPractice.General.OptionsT. The check is a guess, as in Coder.
 * It reads a string that is more than three characters long and not a number
 * as a label, because a key or a value of a list is not translated.
 */
final class UntranslatedOptionsRule implements Rule
{
    /**
     * The `#type` values whose `#options` hold labels.
     */
    private const TYPES = ['checkboxes', 'radios', 'select', 'tableselect'];

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/untranslated-options',
            name: 'Untranslated options',
            description: 'Reports a plain string in the #options of a checkboxes, radios, select or tableselect element.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Array, NodeKind::LegacyArray],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        $this->gate ??= new FileGate(needles: ['#options']);
        if (!$this->gate->passes($file)) {
            return;
        }

        // The element is the array that holds both keys. Its `#type` decides
        // whether the options are labels.
        $pairs = ArrayElements::pairs($file, $context->node);
        $options = $this->valueOf($file, $pairs, '#options');
        $type = $this->valueOf($file, $pairs, '#type');
        if ($options === null || $type === null) {
            return;
        }

        if (!in_array(Values::literalString($file, $type), self::TYPES, strict: true)) {
            return;
        }

        $this->inspectOptions($context, $options);
    }

    /**
     * Returns the value of the first pair with the literal key.
     *
     * @param list<array{Node, Node}> $pairs
     */
    private function valueOf(SourceFile $file, array $pairs, string $key): ?Node
    {
        foreach ($pairs as [$candidate, $value]) {
            if (Values::literalString($file, $candidate) === $key) {
                return $value;
            }
        }

        return null;
    }

    /**
     * Reports the labels in an options array, and in the groups inside it.
     */
    private function inspectOptions(LintContext $context, Node $options): void
    {
        $file = $context->file;
        foreach (ArrayElements::pairs($file, $options) as [, $value]) {
            if ($value->kind === NodeKind::Array || $value->kind === NodeKind::LegacyArray) {
                $this->inspectOptions($context, $value);

                continue;
            }

            // A value that is not a plain literal, such as t() or a variable,
            // is not in the string kind.
            $text = Values::literalString($file, $value);
            if ($text === null || strlen($file->getText($value)) <= 5 || is_numeric($text)) {
                continue;
            }

            $context->report(Issue::new('Pass the #options label through t().', $value->span));
        }
    }
}
