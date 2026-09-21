<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\Nodes;
use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function in_array;
use function ltrim;
use function str_contains;
use function strtolower;
use function trim;

/**
 * Checks the shape of render array callbacks.
 *
 * Ports the syntactic half of phpstan-drupal's RenderCallbackRule. Since
 * Drupal 8.8 a callback must be trusted: a closure, a `service:method`
 * string, or a class method. Drupal rejects a bare function name string at
 * run time.
 *
 * @see https://www.drupal.org/node/2966725
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class RenderCallbackRule implements Rule
{
    private const LIST_KEYS = ['#pre_render', '#post_render', '#date_time_callbacks', '#date_date_callbacks'];

    private const SINGLE_KEYS = ['#access_callback', '#lazy_builder'];

    private const LINK = 'https://www.drupal.org/node/2966725';

    /**
     * Kinds that hold a namespace's name.
     */
    private const IDENTIFIER_KINDS = [
        NodeKind::Identifier,
        NodeKind::LocalIdentifier,
        NodeKind::QualifiedIdentifier,
        NodeKind::FullyQualifiedIdentifier,
    ];

    /**
     * Classes that pass `#lazy_builder` through `array_intersect_key()`, with
     * a boolean in the callback position. phpstan-drupal exempts these classes
     * and their subclasses. Without type information, the rule exempts only
     * the classes themselves.
     */
    private const LAZY_BUILDER_HOSTS = [
        'drupal\core\render\placeholdergenerator',
        'drupal\core\render\renderer',
        'drupal\tests\core\render\rendererplaceholderstest',
    ];

    private readonly FileGate $gate;

    public function __construct()
    {
        // Almost no file has a callback key, so the rule checks the file
        // once before it reads any element.
        $this->gate = new FileGate(needles: [...self::LIST_KEYS, ...self::SINGLE_KEYS]);
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/render-callback',
            name: 'Render callback shape',
            description: 'Reports a render array callback that is not a closure, a service:method string or a class method.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // `KeyValueArrayElement` is a target so that Rust collects every
            // element into the file's target list. The Program pass reads that
            // list. The per-element dispatches do nothing. A dispatched
            // element's snapshot holds only its own subtree, and the
            // `#lazy_builder` exemption must know the class of the element.
            targets: [NodeKind::Program, NodeKind::KeyValueArrayElement],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $file = $context->file;
        if (!$this->gate->passes($file)) {
            return;
        }

        foreach ($file->getTargetNodes() as $element) {
            if ($element->kind !== NodeKind::KeyValueArrayElement) {
                continue;
            }

            $this->checkElement($context, $element);
        }
    }

    /**
     * Checks one `key => value` element for a callback key.
     */
    private function checkElement(LintContext $context, Node $element): void
    {
        $file = $context->file;
        $children = $file->getChildren($element);
        $keyNode = $children[0] ?? null;
        $valueNode = $children[count($children) - 1] ?? null;
        if ($keyNode === null || $valueNode === null || $keyNode === $valueNode) {
            return;
        }

        // The rule unwraps the value only after the key shows a callback
        // element. An unwrap of every element in the file takes more time
        // than the key check, and the key check rejects almost all of them.
        $key = self::literal($file, $keyNode);
        if ($key === '#access_callback') {
            $this->checkCallback($context, Values::unwrap($file, $valueNode), $key);

            return;
        }

        if ($key === '#lazy_builder') {
            if (!in_array($this->declaringClass($file, $element), self::LAZY_BUILDER_HOSTS, strict: true)) {
                $this->checkLazyBuilder($context, Values::unwrap($file, $valueNode));
            }

            return;
        }

        if (!in_array($key, self::LIST_KEYS, strict: true)) {
            return;
        }

        $value = Values::unwrap($file, $valueNode);

        if ($value->kind !== NodeKind::Array) {
            $context->report(Issue::new(
                "The \"{$key}\" render array key expects an array of callbacks.",
                $valueNode->span,
            )->withLink(self::LINK));

            return;
        }

        foreach (self::elements($file, $value) as $element) {
            $this->checkCallback($context, $element, $key);
        }
    }

    /**
     * `#lazy_builder` takes one `[callback, [arguments]]` pair.
     */
    private function checkLazyBuilder(LintContext $context, Node $value): void
    {
        if ($value->kind !== NodeKind::Array) {
            $context->report(Issue::new(
                'The "#lazy_builder" render array key expects a callable array with arguments.',
                $value->span,
            )->withLink(self::LINK));

            return;
        }

        $callback = self::elements($context->file, $value)[0] ?? null;
        if ($callback !== null) {
            $this->checkCallback($context, $callback, '#lazy_builder');
        }
    }

    /**
     * A callback is a closure, a `service:method` or `Class::method` string,
     * or a `[class, 'method']` array. The rule skips every other value that
     * the analyzer cannot check. A bare function name string is the value
     * that breaks.
     */
    private function checkCallback(LintContext $context, Node $callback, string $key): void
    {
        if ($callback->kind !== NodeKind::LiteralString) {
            return;
        }

        $text = self::literal($context->file, $callback) ?? '';
        if (str_contains($text, ':')) {
            return;
        }

        $context->report(Issue::new(
            "The \"{$key}\" callback \"{$text}\" is a plain function name. Drupal does not trust it.",
            $callback->span,
        )->withHelp(
            'Use a closure, a "service:method" string, or a static method on a class that implements TrustedCallbackInterface.',
        )->withLink(self::LINK));
    }

    /**
     * The lowercased fully qualified name of the class that holds a node.
     *
     * Mago's resolved-name table covers references, not declarations, so the
     * rule builds the name from the enclosing namespace and the class name.
     */
    private function declaringClass(SourceFile $file, Node $node): ?string
    {
        $class = $this->ancestor($file, $node, NodeKind::Class_);
        $name = $class === null ? null : Nodes::declaredName($file, $class);
        if ($name === null) {
            return null;
        }

        $namespace = $this->ancestor($file, $node, NodeKind::Namespace);
        foreach ($namespace === null ? [] : $file->getChildren($namespace) as $child) {
            if (!in_array($child->kind, self::IDENTIFIER_KINDS, strict: true)) {
                continue;
            }

            return strtolower(ltrim(trim($file->getText($child)), characters: '\\') . '\\' . $name);
        }

        return strtolower($name);
    }

    /**
     * The closest ancestor of $node with the given kind.
     */
    private function ancestor(SourceFile $file, Node $node, NodeKind $kind): ?Node
    {
        $parent = $file->getParent($node);
        while ($parent !== null && $parent->kind !== $kind) {
            $parent = $file->getParent($parent);
        }

        return $parent;
    }

    /**
     * The unwrapped value of every element of an array literal.
     *
     * @return list<Node>
     */
    private static function elements(SourceFile $file, Node $array): array
    {
        $values = [];
        foreach ($file->getChildren($array) as $child) {
            if ($child->kind !== NodeKind::ArrayElement) {
                continue;
            }

            $element = $file->getChildren($child)[0] ?? null;
            $parts = $element === null ? [] : $file->getChildren($element);
            $value = $parts[count($parts) - 1] ?? null;
            if ($value !== null) {
                $values[] = Values::unwrap($file, $value);
            }
        }

        return $values;
    }

    private static function literal(SourceFile $file, Node $node): ?string
    {
        $unwrapped = Values::unwrap($file, $node);

        return $unwrapped->kind === NodeKind::LiteralString ? Values::literalString($file, $unwrapped) : null;
    }
}
