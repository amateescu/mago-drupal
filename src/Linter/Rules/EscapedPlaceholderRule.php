<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\ArrayElements;
use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\Instantiations;
use amateescu\MagoDrupal\Internal\Invocation;
use amateescu\MagoDrupal\Internal\Nodes;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function rtrim;
use function strrpos;
use function strtolower;
use function substr;
use function trim;

/**
 * Reports check_plain() or Html::escape() as the value of an `@`, `%` or `:`
 * placeholder.
 *
 * Ports DrupalPractice.FunctionCalls.TCheckPlain. These placeholders escape
 * their value, so an escaped value is escaped twice and shows its entities,
 * such as `&amp;amp;`.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class EscapedPlaceholderRule implements Rule
{
    /**
     * The functions that take placeholders, mapped to the position of the
     * placeholder array.
     */
    private const FUNCTIONS = ['t' => 1, 'watchdog' => 2];

    /**
     * The methods that take placeholders, mapped to the position of the
     * placeholder array.
     */
    private const METHODS = ['t' => 1, 'formatplural' => 3];

    /**
     * The classes that take placeholders, by basename, mapped to the position
     * of the placeholder array in the constructor.
     */
    private const CLASSES = [
        'formattablemarkup' => 1,
        'pluraltranslatablemarkup' => 3,
        'translatablemarkup' => 1,
        'translationwrapper' => 1,
    ];

    /**
     * The placeholder prefixes that escape the value. A `!` placeholder of
     * Drupal 7 inserts its value as it is, so it needs the escape.
     */
    private const ESCAPING_PREFIXES = ['@', '%', ':'];

    private const ARRAY_KINDS = [NodeKind::Array, NodeKind::LegacyArray];

    private const HTML = 'drupal\component\utility\html';

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/escaped-placeholder',
            name: 'Escaped placeholder',
            description: 'Reports check_plain() or Html::escape() as the value of an @, % or : placeholder, which escapes the value already.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [...Calls::CALL_KINDS, NodeKind::Instantiation],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        $this->gate ??= new FileGate(needles: ['check_plain', '::escape']);
        if (!$this->gate->passes($file)) {
            return;
        }

        $position = self::placeholderPosition($file, $context->node);
        if ($position === null) {
            return;
        }

        $placeholders = Invocation::fromNode($file, $context->node)?->argument($position);
        if ($placeholders === null || !in_array($placeholders->kind, self::ARRAY_KINDS, strict: true)) {
            return;
        }

        foreach (ArrayElements::pairs($file, $placeholders) as [$key, $value]) {
            $placeholder = Values::literalString($file, $key);
            $prefix = substr($placeholder ?? '', offset: 0, length: 1);
            if (!in_array($prefix, self::ESCAPING_PREFIXES, strict: true)) {
                continue;
            }

            // A call that comes after a `.` escapes part of a longer value, and
            // Coder allows it. The first operand doesn't have a `.` before it.
            $call = Values::leftmost($file, $value);
            $callee = self::escapeCallee($file, $call);
            if ($callee === null) {
                continue;
            }

            $issue = Issue::new(
                "Pass the value of {$placeholder} without {$callee}. The placeholder escapes it already.",
                $call->span,
            )->withHelp('A value escaped twice shows HTML entities, such as &amp;amp;, to the user.');
            $edit = $call === $value ? self::unwrap($file, $call) : null;
            $context->report($edit === null ? $issue : $issue->withEdit($edit));
        }
    }

    /**
     * Returns the position of the placeholder array of a call or
     * instantiation, or null when it doesn't take placeholders.
     */
    private static function placeholderPosition(SourceFile $file, Node $node): ?int
    {
        if ($node->kind === NodeKind::FunctionCall) {
            $name = Calls::matchWanted($file, $node, ['t' => true, 'watchdog' => true]);

            return $name === null ? null : self::FUNCTIONS[$name] ?? null;
        }

        if ($node->kind === NodeKind::Instantiation) {
            $name = Instantiations::writtenNameFast($file, $node) ?? Instantiations::name($file, $node);
            if ($name === null) {
                return null;
            }

            $separator = strrpos($name, needle: '\\');
            $basename = $separator === false ? $name : substr($name, $separator + 1);

            return self::CLASSES[strtolower($basename)] ?? null;
        }

        $name = Calls::writtenNameFast($file, $node);

        return $name === null ? null : self::METHODS[strtolower($name)] ?? null;
    }

    /**
     * Returns how the message names the escape function that a node calls,
     * or null when it calls something else. `Html` must resolve to
     * `Drupal\Component\Utility\Html`, through an import or written in full.
     */
    private static function escapeCallee(SourceFile $file, Node $node): ?string
    {
        if ($node->kind === NodeKind::FunctionCall) {
            return Calls::matchWanted($file, $node, ['check_plain' => true]) === null ? null : 'check_plain()';
        }

        if ($node->kind !== NodeKind::StaticMethodCall) {
            return null;
        }

        if (strtolower(Calls::writtenNameFast($file, $node) ?? '') !== 'escape') {
            return null;
        }

        $class = $file->getChildren($node)[0] ?? null;
        $name = $class === null ? null : Nodes::resolved($file, $class);

        return $name !== null && strtolower($name) === self::HTML ? 'Html::escape()' : null;
    }

    /**
     * Replaces the escape call with its argument. The fix changes what users
     * see, so it is potentially unsafe. A call with another argument count, a
     * named or spread argument, or a comment inside the parentheses doesn't
     * get a fix.
     */
    private static function unwrap(SourceFile $file, Node $call): ?TextEdit
    {
        $view = CallExpression::fromNode($file, $call);
        if (count($view->arguments) !== 1) {
            return null;
        }

        $argument = $view->arguments[0];
        if ($argument->name !== null || $argument->unpacked) {
            return null;
        }

        $list = $file->getChildren($call)[$call->kind === NodeKind::FunctionCall ? 1 : 2] ?? null;
        if ($list === null) {
            return null;
        }

        $text = $file->getText($argument->node);
        $inside = trim(rtrim(trim(substr($file->getText($list), offset: 1, length: -1)), characters: ','));
        if ($inside !== $text) {
            return null;
        }

        return TextEdit::replace($call->span, $text)->withSafety(Safety::PotentiallyUnsafe);
    }
}
