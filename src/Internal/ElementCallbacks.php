<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function count;
use function explode;
use function in_array;
use function ltrim;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function strtolower;
use function strtr;
use function substr;
use function trim;

/**
 * Finds the callbacks a file writes under render and form array keys.
 *
 * Covers a key in an array literal (`'#submit' => ['::save']`), an
 * assignment (`$form['#submit'] = [...]`), an append
 * (`$form['actions']['submit']['#submit'][] = '::save'`),
 * `array_unshift()` and `array_push()` on the key, the literal lists of an
 * `array_merge()` assigned to it, the `callback` of an `#ajax` array and the
 * `exists` of a `#machine_name` array.
 * Only literal callbacks come back: a string, a `[class, 'method']` or
 * `[$object, 'method']` pair, or a class name joined to `'::method'`.
 * Closures, variables, service notation and anything computed are left
 * out.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ElementCallbacks
{
    /**
     * Every key, quoted, for a check of the file text before any syntax is
     * fetched.
     */
    public const GATE =
        '/[\'"]#(?:pre_render|post_render|lazy_builder|access_callback|date_date_callbacks|date_time_callbacks'
            . '|propsAlter|slotsAlter|validate|submit|element_validate|process|after_build|entity_builders|ajax'
            . '|value_callback|machine_name|file_value_callbacks)[\'"]/';

    private const LAZY_BUILDER = '#lazy_builder';

    /**
     * Keys whose array holds the callback under one element, and the name of
     * that element.
     */
    private const NESTED = ['#ajax' => 'callback', '#machine_name' => 'exists'];

    /**
     * Functions whose arguments after the first go into the array the first
     * one names.
     */
    private const ADDERS = ['array_unshift', 'array_push'];

    /**
     * A variable, or an escape a double-quoted string turns into something
     * else, after any doubled backslash.
     */
    private const DOUBLE_QUOTED_ESCAPE = '/\$|(?<!\\\\)(?:\\\\\\\\)*\\\\[nrtvef$"0-7xu]/';

    private const IDENTIFIER = '/^[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*$/';

    private const CLASS_NAME = '/^\\\\?[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*(?:\\\\[A-Za-z_\x80-\xff][A-Za-z0-9_\x80-\xff]*)*$/';

    /**
     * Wrapper nodes between an expression and what it holds.
     */
    private const WRAPPERS = [
        NodeKind::Expression,
        NodeKind::Literal,
        NodeKind::Access,
        NodeKind::Variable,
        NodeKind::Call,
        NodeKind::Parenthesized,
    ];

    /**
     * Expressions whose type is asked for the class of `[$object, 'method']`.
     */
    private const OBJECTS = [NodeKind::DirectVariable, NodeKind::PropertyAccess, NodeKind::MethodCall];

    /**
     * Where a variable is bound other than by an assignment: a reference,
     * or the target of a foreach. The variable name follows.
     */
    private const REBINDS = '/(?:&\s*+|\bas\s[^;{]*)';

    private const FUNCTION_LIKES = [NodeKind::Method, NodeKind::Function, NodeKind::Closure, NodeKind::ArrowFunction];

    private const CLASS_LIKES = [
        NodeKind::Class_,
        NodeKind::Trait,
        NodeKind::Interface,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    /**
     * The file's assignments, listed on the first variable callback.
     *
     * @var list<Node>|null
     */
    private ?array $assignments = null;

    private function __construct(
        private readonly SourceFile $file,
    ) {}

    /**
     * @return list<ElementCallback>
     */
    public static function in(SourceFile $file): array
    {
        $walk = new self($file);
        $callbacks = [];
        foreach ($file->getNodes(NodeKind::LiteralString) as $literal) {
            // Almost every literal fails this byte compare, so few are
            // decoded at all.
            $key = ($file->contents[$literal->span->start + 1] ?? '') === '#' ? $walk->literal($literal) : null;
            $use = $key === null ? null : self::use($key);
            if ($key === null || $use === null) {
                continue;
            }

            foreach ($walk->values($literal, $key) as $value) {
                $callback = $walk->callback($key, $use, $value);
                if ($callback !== null) {
                    $callbacks[] = $callback;
                }
            }
        }

        return $callbacks;
    }

    /**
     * How core runs the callbacks under a key, or null for any other key.
     */
    public static function use(string $key): ?CallbackUse
    {
        return match ($key) {
            '#pre_render', '#post_render', '#access_callback', self::LAZY_BUILDER => CallbackUse::Render,
            '#date_date_callbacks',
            '#date_time_callbacks',
            '#propsAlter',
            '#slotsAlter',
                => CallbackUse::TrustedCallable,
            '#validate',
            '#submit',
            '#element_validate',
            '#process',
            '#after_build',
            '#entity_builders',
            '#ajax',
                => CallbackUse::Form,
            '#value_callback' => CallbackUse::Value,
            '#machine_name', '#file_value_callbacks' => CallbackUse::Direct,
            default => null,
        };
    }

    /**
     * Whether the key holds a list of callbacks. The others hold one, or
     * one inside an array: `[callback, arguments]` for a lazy builder, and
     * one element of a nested array, such as the `callback` of `#ajax`.
     */
    private static function isList(string $key): bool
    {
        return (
            !in_array($key, ['#access_callback', '#value_callback', self::LAZY_BUILDER], strict: true)
            && !array_key_exists($key, self::NESTED)
        );
    }

    /**
     * The callback value nodes the key literal leads to.
     *
     * @return list<Node>
     */
    private function values(Node $literal, string $key): array
    {
        [$child, $holder] = $this->outer($literal);
        $parts = $holder === null ? [] : $this->file->getChildren($holder);
        if ($holder?->kind === NodeKind::KeyValueArrayElement && ($parts[0] ?? null)?->id === $child->id) {
            return $this->held($key, $parts[count($parts) - 1]);
        }

        return (
            $holder?->kind === NodeKind::ArrayAccess && ($parts[1] ?? null)?->id === $child->id
                ? $this->accessed($key, $holder)
                : []
        );
    }

    /**
     * The callbacks written through `$element['#key']`: assigned to it,
     * appended to it, set under an index of it, or added with
     * `array_unshift()` or `array_push()`.
     *
     * @return list<Node>
     */
    private function accessed(string $key, Node $access): array
    {
        [$wrapper, $parent] = $this->outer($access);
        $value = $this->assigned($wrapper, $parent);
        if ($value !== null) {
            return $this->held($key, $value);
        }

        if ($parent?->kind === NodeKind::PositionalArgument) {
            return self::isList($key) ? $this->added($parent) : [];
        }

        $value = match ($parent?->kind) {
            NodeKind::ArrayAppend => self::isList($key) ? $this->assigned(...$this->outer($parent)) : null,
            NodeKind::ArrayAccess => $this->indexed($key, $wrapper, $parent),
            default => null,
        };

        return $value === null ? [] : [$value];
    }

    /**
     * The value assigned under an index of the key, as in
     * `$form['#submit']['name'] = '::save'` or
     * `$form['#ajax']['callback'] = '::rebuild'`.
     */
    private function indexed(string $key, Node $wrapper, Node $access): ?Node
    {
        [$array, $index] = $this->file->getChildren($access) + [null, null];
        if ($array?->id !== $wrapper->id || $index === null) {
            return null;
        }

        $nested = self::NESTED[$key] ?? null;
        $listed = self::isList($key) || $nested !== null && $this->literal($this->inner($index)) === $nested;

        return $listed ? $this->assigned(...$this->outer($access)) : null;
    }

    /**
     * The callbacks a key's value holds: each element of a list, the value
     * itself for a single callback, the first element of a lazy builder and
     * the one element of a nested array, such as the `callback` of `#ajax`.
     *
     * @return list<Node>
     */
    private function held(string $key, Node $value): array
    {
        $value = $this->inner($value);
        if ($key === '#access_callback' || $key === '#value_callback') {
            return [$value];
        }

        if (self::isList($key) && $value->kind === NodeKind::FunctionCall) {
            return $this->merged($value);
        }

        if (!self::isArray($value)) {
            return [];
        }

        $elements = $this->elements($value);
        if ($key === self::LAZY_BUILDER) {
            $first = $elements[0] ?? null;

            return $first === null || $first[0] !== null ? [] : [$first[1]];
        }

        $nested = self::NESTED[$key] ?? null;
        $callbacks = [];
        foreach ($elements as [$elementKey, $element]) {
            if ($nested === null) {
                $callbacks[] = $element;
                continue;
            }

            if ($elementKey !== null && $this->literal($elementKey) === $nested) {
                $callbacks[] = $element;
            }
        }

        return $callbacks;
    }

    /**
     * The elements of the array literals an `array_merge()` call takes, as in
     * `'#submit' => array_merge($submit, ['::save'])`.
     *
     * @return list<Node>
     */
    private function merged(Node $call): array
    {
        [$name, $list] = $this->file->getChildren($call) + [null, null];
        if (
            $name === null
            || $list === null
            || strtolower(ltrim(trim($this->file->getText($name)), characters: '\\')) !== 'array_merge'
        ) {
            return [];
        }

        $values = [];
        foreach ($this->file->getChildren($list) as $argument) {
            $positional = $this->file->getChildren($argument)[0] ?? null;
            $value = $positional?->kind === NodeKind::PositionalArgument
                ? $this->file->getChildren($positional)[0] ?? null
                : null;
            $value = $value === null ? null : $this->inner($value);
            if ($value === null || !self::isArray($value)) {
                continue;
            }

            foreach ($this->elements($value) as [, $element]) {
                $values[] = $element;
            }
        }

        return $values;
    }

    /**
     * The value assigned with `=` when $target is the left side of the
     * assignment $parent.
     */
    private function assigned(Node $target, ?Node $parent): ?Node
    {
        if ($parent === null || $parent->kind !== NodeKind::Assignment) {
            return null;
        }

        $parts = $this->file->getChildren($parent);
        $operator = $parts[1] ?? null;
        if (
            ($parts[0] ?? null)?->id !== $target->id
            || $operator === null
            || trim($this->file->getText($operator)) !== '='
        ) {
            return null;
        }

        $value = $parts[2] ?? null;

        return $value === null ? null : $this->inner($value);
    }

    /**
     * The arguments after the first of `array_unshift()` or `array_push()`,
     * when the first one is $argument.
     *
     * @return list<Node>
     */
    private function added(Node $argument): array
    {
        $wrapper = $this->file->getParent($argument);
        $list = $wrapper === null ? null : $this->file->getParent($wrapper);
        $call = $list === null ? null : $this->file->getParent($list);
        if (
            $wrapper === null
            || $list === null
            || $call === null
            || $list->kind !== NodeKind::ArgumentList
            || $call->kind !== NodeKind::FunctionCall
        ) {
            return [];
        }

        $name = $this->file->getChildren($call)[0] ?? null;
        $function = $name === null ? '' : strtolower(ltrim(trim($this->file->getText($name)), characters: '\\'));
        $arguments = $this->file->getChildren($list);
        if (!in_array($function, self::ADDERS, strict: true) || ($arguments[0] ?? null)?->id !== $wrapper->id) {
            return [];
        }

        $values = [];
        foreach ($arguments as $index => $other) {
            $positional = $this->file->getChildren($other)[0] ?? null;
            $value = $positional?->kind === NodeKind::PositionalArgument
                ? $this->file->getChildren($positional)[0] ?? null
                : null;
            if ($index !== 0 && $value !== null) {
                $values[] = $this->inner($value);
            }
        }

        return $values;
    }

    /**
     * Reads one callback value, or null when it names nothing statically.
     */
    private function callback(string $key, CallbackUse $use, Node $value): ?ElementCallback
    {
        $value = $this->inner($value);

        return match ($value->kind) {
            NodeKind::LiteralString => $this->fromString($key, $use, $value),
            NodeKind::Binary => $this->fromConcatenation($key, $use, $value),
            NodeKind::Array, NodeKind::LegacyArray => $this->fromArray($key, $use, $value),
            default => null,
        };
    }

    /**
     * `[Class::class, 'method']`, `['Class', 'method']`, `[$this, 'method']`,
     * `[$class, 'method']` after `$class = get_class($this)` and the like,
     * and `[$object, 'method']`.
     */
    private function fromArray(string $key, CallbackUse $use, Node $value): ?ElementCallback
    {
        $elements = $this->elements($value);
        [$first, $second] = count($elements) === 2 ? $elements : [[null, null], [null, null]];
        $name = $first[0] === null && $second[0] === null && $second[1] !== null ? $this->literal($second[1]) : null;
        $target = $first[1];
        if ($name === null || $target === null || preg_match(self::IDENTIFIER, $name) !== 1) {
            return null;
        }

        if ($target->kind === NodeKind::DirectVariable && $this->file->getText($target) === '$this') {
            $class = $this->enclosingClass($value);

            return $class === null
                ? null
                : new ElementCallback($key, $use, $value, CallStyle::ObjectArray, $name, $class, late: true);
        }

        $class = $target->kind === NodeKind::DirectVariable ? $this->assignedClass($target) : $this->classOf($target);
        if ($class !== null) {
            return new ElementCallback($key, $use, $value, CallStyle::ClassArray, $name, $class[0], $class[1]);
        }

        return in_array($target->kind, self::OBJECTS, strict: true)
            ? new ElementCallback($key, $use, $value, CallStyle::ObjectArray, $name, object: $target)
            : null;
    }

    /**
     * `'::method'`, `'Class::method'` or `'function_name'`. A `'::method'`
     * comes back outside a class too, since it is wrong on any key but the
     * form keys wherever it is written.
     */
    private function fromString(string $key, CallbackUse $use, Node $value): ?ElementCallback
    {
        $text = $this->literal($value);
        if ($text === null || $text === '') {
            return null;
        }

        if (str_starts_with($text, '::')) {
            $name = substr($text, offset: 2);

            return preg_match(self::IDENTIFIER, $name) === 1
                ? new ElementCallback(
                    $key,
                    $use,
                    $value,
                    CallStyle::FormObject,
                    $name,
                    $this->enclosingClass($value),
                    late: true,
                    inHook: $this->inHook($value),
                )
                : null;
        }

        if (str_contains($text, '::')) {
            [$class, $name] = explode('::', $text, limit: 2);
            if (preg_match(self::CLASS_NAME, $class) !== 1 || preg_match(self::IDENTIFIER, $name) !== 1) {
                return null;
            }

            /** @var non-empty-string $class */
            $class = ltrim($class, characters: '\\');

            return new ElementCallback($key, $use, $value, CallStyle::ClassString, $name, $class);
        }

        return preg_match(self::IDENTIFIER, $text) === 1
            ? new ElementCallback($key, $use, $value, CallStyle::Function, $text)
            : null;
    }

    /**
     * `static::class . '::method'` and the same with `self::class`,
     * `Foo::class` or `__CLASS__`.
     */
    private function fromConcatenation(string $key, CallbackUse $use, Node $value): ?ElementCallback
    {
        $parts = $this->file->getChildren($value);
        if (count($parts) !== 3 || trim($this->file->getText($parts[1])) !== '.') {
            return null;
        }

        $class = $this->classOf($this->inner($parts[0]));
        $right = $this->literal($this->inner($parts[2]));
        if ($class === null || $right === null || !str_starts_with($right, '::')) {
            return null;
        }

        $name = substr($right, offset: 2);

        return preg_match(self::IDENTIFIER, $name) === 1
            ? new ElementCallback($key, $use, $value, CallStyle::ClassString, $name, $class[0], $class[1])
            : null;
    }

    /**
     * The class an expression names as a string, with whether it is only
     * known at run time: `Foo::class`, `self::class`, `static::class`,
     * `__CLASS__` or a class name literal.
     *
     * @return array{non-empty-string, bool}|null
     */
    private function classOf(Node $node): ?array
    {
        if ($node->kind === NodeKind::LiteralString) {
            $text = $this->literal($node);
            if ($text === null || preg_match(self::CLASS_NAME, $text) !== 1) {
                return null;
            }

            /** @var non-empty-string $class */
            $class = ltrim($text, characters: '\\');

            return [$class, false];
        }

        if ($node->kind === NodeKind::MagicConstant) {
            $class = strtolower(trim($this->file->getText($node))) === '__class__'
                ? $this->enclosingClass($node)
                : null;

            return $class === null ? null : [$class, false];
        }

        if ($node->kind === NodeKind::FunctionCall) {
            return $this->calledClass($node);
        }

        $parts = $node->kind === NodeKind::ClassConstantAccess ? $this->file->getChildren($node) : [];
        $selector = $parts[1] ?? null;
        if ($selector === null || strtolower(trim($this->file->getText($selector))) !== 'class') {
            return null;
        }

        $subject = $this->inner($parts[0]);
        $keyword = strtolower(trim($this->file->getText($subject)));
        if ($subject->kind === NodeKind::Keyword) {
            $class = $keyword === 'self' || $keyword === 'static' ? $this->enclosingClass($node) : null;

            return $class === null ? null : [$class, $keyword === 'static'];
        }

        $class = $subject->kind === NodeKind::Identifier ? Nodes::resolved($this->file, $subject) : null;

        return $class === null ? null : [$class, false];
    }

    /**
     * The class a variable holds when the function it is used in assigns it
     * once, with `=`, from an expression classOf() reads, as element info
     * does with `$class = get_class($this)`. Mago types get_class() as a
     * plain string, so the variable's type names no class. A variable that a
     * foreach or a reference may bind as well is left alone.
     *
     * @return array{non-empty-string, bool}|null
     */
    private function assignedClass(Node $variable): ?array
    {
        $function = $this->ancestor($variable, self::FUNCTION_LIKES);
        $name = trim($this->file->getText($variable));
        $quoted = preg_quote($name, delimiter: '/') . '\b/';
        if ($function === null || preg_match(self::REBINDS . $quoted, $this->file->getText($function)) === 1) {
            return null;
        }

        $value = null;
        $this->assignments ??= $this->file->getNodes(NodeKind::Assignment);
        foreach ($this->assignments as $assignment) {
            [$left, $operator, $right] = $this->file->getChildren($assignment) + [null, null, null];
            if (
                $left === null
                || $assignment->span->start < $function->span->start
                || $assignment->span->end > $function->span->end
                || preg_match('/' . $quoted, $this->file->getText($left)) !== 1
            ) {
                continue;
            }

            // Assigned twice, with another operator, or through a list.
            if (
                $value !== null
                || $operator === null
                || $right === null
                || trim($this->file->getText($left)) !== $name
                || trim($this->file->getText($operator)) !== '='
            ) {
                return null;
            }

            $value = $this->inner($right);
        }

        return $value === null ? null : $this->classOf($value);
    }

    /**
     * `get_called_class()` and `get_class($this)`, which name the class of
     * the object at run time, and `get_class()`, which names the class the
     * call is written in.
     *
     * @return array{non-empty-string, bool}|null
     */
    private function calledClass(Node $call): ?array
    {
        [$name, $list] = $this->file->getChildren($call) + [null, null];
        $function = $name === null ? '' : strtolower(ltrim(trim($this->file->getText($name)), characters: '\\'));
        $arguments = $list === null ? [] : $this->file->getChildren($list);
        $argument = count($arguments) === 1 ? trim($this->file->getText($arguments[0])) : null;
        $late = match (true) {
            $function === 'get_called_class' && $arguments === [],
            $function === 'get_class' && $argument === '$this',
                => true,
            $function === 'get_class' && $arguments === [] => false,
            default => null,
        };
        $class = $late === null ? null : $this->enclosingClass($call);

        return $class === null || $late === null ? null : [$class, $late];
    }

    /**
     * The fully qualified name of the named class the node is written in, or
     * null inside a trait, an interface, an enum, an anonymous class or
     * outside any class.
     *
     * @return non-empty-string|null
     */
    private function enclosingClass(Node $node): ?string
    {
        $class = $this->ancestor($node, self::CLASS_LIKES);
        if ($class === null || $class->kind !== NodeKind::Class_) {
            return null;
        }

        $identifier = Nodes::declaredIdentifier($this->file, $class);

        return $identifier === null ? null : Nodes::resolved($this->file, $identifier);
    }

    /**
     * The elements of an array literal as key and value pairs, the key null
     * for a list element. Spread elements are left out.
     *
     * @return list<array{Node|null, Node}>
     */
    private function elements(Node $array): array
    {
        $elements = [];
        foreach ($this->file->getChildren($array) as $wrapper) {
            if ($wrapper->kind !== NodeKind::ArrayElement) {
                continue;
            }

            $element = $this->file->getChildren($wrapper)[0] ?? null;
            $parts = $element === null ? [] : $this->file->getChildren($element);
            $pair = match (true) {
                $element?->kind === NodeKind::KeyValueArrayElement && count($parts) >= 2 => [
                    $this->inner($parts[0]),
                    $this->inner($parts[count($parts) - 1]),
                ],
                $element?->kind === NodeKind::ValueArrayElement && $parts !== [] => [null, $this->inner($parts[0])],
                default => null,
            };
            if ($pair !== null) {
                $elements[] = $pair;
            }
        }

        return $elements;
    }

    /**
     * Whether the method the node is written in carries `#[Hook]`.
     */
    private function inHook(Node $node): bool
    {
        $method = $this->ancestor($node, [NodeKind::Method, ...self::CLASS_LIKES]);
        foreach ($method?->kind === NodeKind::Method ? $this->file->getChildren($method) : [] as $child) {
            if ($child->kind !== NodeKind::AttributeList) {
                continue;
            }

            foreach ($this->file->getResolvedNames($child) as $name) {
                if (strtolower(ltrim($name->name, characters: '\\')) === strtolower(Attributes::HOOK)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The first ancestor of one of the kinds.
     *
     * @param list<NodeKind> $kinds
     */
    private function ancestor(Node $node, array $kinds): ?Node
    {
        $parent = $this->file->getParent($node);
        while ($parent !== null && !in_array($parent->kind, $kinds, strict: true)) {
            $parent = $this->file->getParent($parent);
        }

        return $parent;
    }

    /**
     * The node inside the wrappers around an expression. Each wrapper,
     * parentheses included, has the expression as its only child.
     */
    private function inner(Node $node): Node
    {
        while (in_array($node->kind, self::WRAPPERS, strict: true)) {
            $child = $this->file->getChildren($node)[0] ?? null;
            if ($child === null) {
                break;
            }

            $node = $child;
        }

        return $node;
    }

    /**
     * The first node above the wrappers around $node, with the wrapper just
     * below it.
     *
     * @return array{Node, Node|null}
     */
    private function outer(Node $node): array
    {
        $child = $node;
        $parent = $this->file->getParent($node);
        while ($parent !== null && in_array($parent->kind, self::WRAPPERS, strict: true)) {
            $child = $parent;
            $parent = $this->file->getParent($parent);
        }

        return [$child, $parent];
    }

    private static function isArray(Node $node): bool
    {
        return $node->kind === NodeKind::Array || $node->kind === NodeKind::LegacyArray;
    }

    /**
     * The value of a string literal. The analyzer snapshot carries no decoded
     * literals, so a single-quoted one is unescaped here, and a double-quoted
     * one only counts without variables and without escapes other than a
     * doubled backslash.
     */
    private function literal(Node $node): ?string
    {
        if ($node->kind !== NodeKind::LiteralString) {
            return null;
        }

        $text = $this->file->getText($node);
        $inner = substr($text, offset: 1, length: -1);
        if (str_starts_with($text, "'")) {
            return strtr($inner, ['\\\\' => '\\', "\\'" => "'"]);
        }

        // PHP keeps a backslash before any other character, so a class name
        // such as "\Drupal\node\Node" stays as written.
        return (
            str_starts_with($text, '"') && preg_match(self::DOUBLE_QUOTED_ESCAPE, $inner) !== 1
                ? strtr($inner, ['\\\\' => '\\'])
                : null
        );
    }
}
