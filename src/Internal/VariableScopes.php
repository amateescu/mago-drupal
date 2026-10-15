<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function in_array;
use function ltrim;
use function preg_match;
use function str_contains;
use function stripos;
use function strtolower;
use function substr;

/**
 * Collects the variables that each scope of a file writes and reads, the way
 * Coder's VariableAnalysis sniff does.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 *
 * @internal
 */
final class VariableScopes
{
    /**
     * The names that are never reported: `$this` and the superglobals that
     * Coder skips.
     */
    private const SKIPPED = [
        '$this' => true,
        '$GLOBALS' => true,
        '$_SERVER' => true,
        '$_GET' => true,
        '$_POST' => true,
        '$_FILES' => true,
        '$_COOKIE' => true,
        '$_SESSION' => true,
        '$_REQUEST' => true,
        '$_ENV' => true,
        '$argv' => true,
        '$argc' => true,
        '$http_response_header' => true,
        '$HTTP_RAW_POST_DATA' => true,
    ];

    /**
     * A variable followed by one of these is read: a property or method access,
     * an index or a call. A write never has one right after the name, so the
     * variable's parents aren't needed.
     */
    private const READ_AFTER = '/\G(?:->|\?->|::|[\[(])/';

    /**
     * The nodes that stop the search for an enclosing assignment or call.
     */
    private const STATEMENT_ENDS = [
        NodeKind::Statement,
        NodeKind::Block,
        NodeKind::Function,
        NodeKind::Method,
        NodeKind::Closure,
        NodeKind::Program,
    ];

    /**
     * A list element that is a write.
     */
    private const LIST_WRITE = 'write';

    /**
     * A list element that is the value of a `$key => $value` foreach.
     */
    private const LIST_FOREACH_VALUE = 'foreach value';

    /**
     * The variables of each scope, keyed by the id of the scope's node.
     *
     * @var array<int, array<string, ScopeVariable>>
     */
    private array $variables = [];

    private readonly FunctionScopes $scopes;

    /**
     * @param non-negative-int $program
     */
    private function __construct(
        private readonly SourceFile $file,
        int $program,
    ) {
        $this->scopes = new FunctionScopes($file, $program);
    }

    /**
     * Returns the variables of each scope of the file.
     *
     * The rule that calls this must target `Program`, so that the snapshot
     * holds the whole file, and the kinds in FunctionScopes::REGIONS.
     *
     * @param non-negative-int $program The id of the file's Program node.
     *
     * @return array<int, array<string, ScopeVariable>>
     */
    public static function of(SourceFile $file, int $program): array
    {
        // drupal/unused-variable and drupal/variable-redeclaration read the
        // same file in a row, so the walk runs once for both.
        return LastFile::get(self::class, $file->contents, static fn(): array => self::collect($file, $program));
    }

    /**
     * Walks the variables of the file into their scopes.
     *
     * @param non-negative-int $program
     *
     * @return array<int, array<string, ScopeVariable>>
     */
    private static function collect(SourceFile $file, int $program): array
    {
        $scopes = new self($file, $program);
        foreach ($file->getNodes(NodeKind::DirectVariable) as $variable) {
            $scopes->variable($variable);
        }

        // `"${name}"` reads $name. Few files write that form.
        if (str_contains($file->contents, '${')) {
            foreach ($file->getNodes(NodeKind::IndirectVariable) as $variable) {
                $scopes->interpolated($variable);
            }
        }

        if (
            stripos($file->contents, needle: 'compact') !== false
            || stripos($file->contents, needle: 'get_defined_vars') !== false
        ) {
            foreach ($file->getNodes(NodeKind::FunctionCall) as $call) {
                $scopes->call($call);
            }
        }

        return $scopes->variables;
    }

    /**
     * Records a variable as a write or a read, by what holds it.
     *
     * A write is the left side of an assignment, an element of a list that
     * is assigned, a foreach key or value, a catch variable, a `static` or
     * `global` declaration, or a variable of a closure's `use` list. Every
     * other variable is a read.
     *
     * @mago-expect lint:halstead
     */
    private function variable(Node $variable): void
    {
        $name = $this->file->getText($variable);
        if (array_key_exists($name, self::SKIPPED)) {
            return;
        }

        $span = $variable->span;
        if (preg_match(self::READ_AFTER, $this->file->contents, offset: $span->end) === 1) {
            $this->find($variable, $name)?->read($span->start);

            return;
        }

        $parent = $this->file->getParent($variable);
        if ($parent === null) {
            return;
        }

        switch ($parent->kind) {
            case NodeKind::FunctionLikeParameter:
                $this->find($variable, $name)?->parameter($span);

                return;

            case NodeKind::ClosureUseClauseVariable:
                $this->binding($variable, $name, $parent);

                return;

            case NodeKind::TryCatchClause:
                $this->find($variable, $name)?->catchVariable($span);

                return;

            case NodeKind::StaticAbstractItem:
                $this->find($variable, $name)?->declare($span, ScopeVariable::STATIC);

                return;

            case NodeKind::StaticConcreteItem:
                // Coder reads `static $x = 1;` as a plain assignment.
                $this->find($variable, $name)?->assign($span);

                return;

            case NodeKind::Variable:
                $this->wrapped($variable, $name, $parent);

                return;
        }

        $this->find($variable, $name)?->read($span->start);
    }

    /**
     * Records a variable of a closure's `use` list. It reads the variable of
     * the enclosing scope. A binding by reference counts as used inside the
     * closure.
     */
    private function binding(Node $variable, string $name, Node $binding): void
    {
        $span = $variable->span;
        $this->find($variable, $name, outside: true)?->read($span->start);
        $bound = $this->find($variable, $name);
        if ($this->file->contents[$binding->span->start] === '&') {
            $bound?->bindByReference($span);

            return;
        }

        $bound?->declare($span, ScopeVariable::BOUND);
    }

    /**
     * Records a variable in a `Variable` node, by what holds it.
     */
    private function wrapped(Node $variable, string $name, Node $wrapper): void
    {
        $span = $variable->span;
        $holder = $this->file->getParent($wrapper);
        if ($holder?->kind === NodeKind::Global) {
            $this->find($variable, $name)?->declare($span, ScopeVariable::GLOBAL);

            return;
        }

        // The property in `Foo::$bar`.
        if ($holder?->kind === NodeKind::StaticPropertyAccess) {
            return;
        }

        $context = $holder?->kind === NodeKind::Expression ? $this->file->getParent($holder) : null;
        if ($holder !== null && $context !== null && $this->written($variable, $name, $holder, $context)) {
            return;
        }

        $this->find($variable, $name)?->read($span->start);
    }

    /**
     * Records a variable as written by its context, and returns whether it
     * is a write.
     *
     * @mago-expect lint:halstead
     */
    private function written(Node $variable, string $name, Node $holder, Node $context): bool
    {
        $span = $variable->span;
        $first = $holder->span->start === $context->span->start;
        switch ($context->kind) {
            case NodeKind::Assignment:
                if (!$first) {
                    return false;
                }

                $found = $this->find($variable, $name);
                if ($found === null) {
                    return true;
                }

                // Coder also counts the write as a read when the assignment
                // is the value of another one, or an argument of a call.
                if ($this->isNested($context)) {
                    $found->read($span->start);
                }

                if ($this->bindsReference($context)) {
                    $found->bindReference($span);

                    return true;
                }

                $found->assign($span);

                return true;

            case NodeKind::ForeachValueTarget:
                $this->find($variable, $name)?->write($span);

                return true;

            case NodeKind::ForeachKeyValueTarget:
                // The value of a `$key => $value` foreach may stay unused.
                $found = $this->find($variable, $name);
                if (!$first) {
                    $found?->allow();

                    return true;
                }

                $found?->write($span);

                return true;

            case NodeKind::UnaryPrefix:
                return $this->reference($variable, $name, $context);

            case NodeKind::ValueArrayElement:
            case NodeKind::KeyValueArrayElement:
                return $this->element($variable, $name, $holder, $context);
        }

        return false;
    }

    /**
     * Records `&$x` as a foreach value or a list element, and returns
     * whether it is one.
     */
    private function reference(Node $variable, string $name, Node $prefix): bool
    {
        $holder = $this->file->getParent($prefix);
        $target = $holder?->kind === NodeKind::Expression ? $this->file->getParent($holder) : null;
        if ($holder === null || $target === null) {
            return false;
        }

        switch ($target->kind) {
            case NodeKind::ForeachValueTarget:
                $this->find($variable, $name)?->foreachReference($variable->span);

                return true;

            case NodeKind::ForeachKeyValueTarget:
                $this->find($variable, $name)?->allow();

                return true;

            case NodeKind::ValueArrayElement:
            case NodeKind::KeyValueArrayElement:
                return $this->element($variable, $name, $holder, $target);
        }

        return false;
    }

    /**
     * Records a variable that is the value of an array element, when the
     * array is a list that is assigned, and returns whether it is one.
     */
    private function element(Node $variable, string $name, Node $holder, Node $element): bool
    {
        // The key of a `'key' => $value` element starts with the element.
        if ($element->kind === NodeKind::KeyValueArrayElement && $holder->span->start === $element->span->start) {
            return false;
        }

        $target = $this->listTarget($element);
        if ($target === null) {
            return false;
        }

        $found = $this->find($variable, $name);
        if ($target === self::LIST_FOREACH_VALUE) {
            $found?->allow();

            return true;
        }

        $found?->write($variable->span);

        return true;
    }

    /**
     * Tells what the list of an array element is: assigned or a foreach
     * value, a `$key => $value` foreach value, or null when the array isn't
     * a list.
     */
    private function listTarget(Node $element): ?string
    {
        $item = $this->file->getParent($element);
        $array = $item === null ? null : $this->file->getParent($item);
        if ($array === null || $array->kind !== NodeKind::Array && $array->kind !== NodeKind::List) {
            return null;
        }

        $holder = $this->file->getParent($array);
        $target = $holder?->kind === NodeKind::Expression ? $this->file->getParent($holder) : null;
        if ($holder === null || $target === null) {
            return null;
        }

        $first = $holder->span->start === $target->span->start;

        return match ($target->kind) {
            NodeKind::Assignment => $first ? self::LIST_WRITE : null,
            NodeKind::ForeachValueTarget => self::LIST_WRITE,
            NodeKind::ForeachKeyValueTarget => $first ? null : self::LIST_FOREACH_VALUE,
            NodeKind::ValueArrayElement => $this->listTarget($target),
            NodeKind::KeyValueArrayElement => $first ? null : $this->listTarget($target),
            default => null,
        };
    }

    /**
     * Whether the right side of an assignment is a reference, as in
     * `$x = &$y`.
     */
    private function bindsReference(Node $assignment): bool
    {
        $right = $this->file->getChildren($assignment)[2] ?? null;
        $value = $right === null ? null : $this->file->getChildren($right)[0] ?? null;

        return $value?->kind === NodeKind::UnaryPrefix && $this->file->contents[$value->span->start] === '&';
    }

    /**
     * Whether an assignment is in the value of another assignment, or in an
     * argument of a call, within its statement.
     */
    private function isNested(Node $assignment): bool
    {
        $child = $assignment;
        $parent = $this->file->getParent($assignment);
        while ($parent !== null && !in_array($parent->kind, self::STATEMENT_ENDS, strict: true)) {
            if (
                $parent->kind === NodeKind::ArgumentList
                || $parent->kind === NodeKind::PartialArgumentList
                || $parent->kind === NodeKind::Assignment && $child->span->start !== $parent->span->start
            ) {
                return true;
            }

            $child = $parent;
            $parent = $this->file->getParent($parent);
        }

        return false;
    }

    /**
     * Records `"${name}"`, which reads $name.
     */
    private function interpolated(Node $variable): void
    {
        $wrapper = $this->file->getParent($variable);
        $holder = $wrapper === null ? null : $this->file->getParent($wrapper);
        $part = $holder === null ? null : $this->file->getParent($holder);
        $expression = $this->file->getChildren($variable)[0] ?? null;
        $identifier = $expression === null ? null : $this->file->getChildren($expression)[0] ?? null;
        if (
            $part?->kind !== NodeKind::StringPart
            || $identifier === null
            || $identifier->kind !== NodeKind::Identifier
        ) {
            return;
        }

        $start = $variable->span->start;
        $this->lookup($start, '$' . $this->file->getText($identifier))?->read($start);
    }

    /**
     * Records the names read by compact(), and the variables read by
     * get_defined_vars(): every one that the scope used before the call.
     */
    private function call(Node $call): void
    {
        $children = $this->file->getChildren($call);
        $callee = $children[0] ?? null;
        $arguments = $children[1] ?? null;
        if ($callee === null || $arguments === null) {
            return;
        }

        $function = strtolower(ltrim($this->file->getText($callee), characters: '\\'));
        $start = $call->span->start;
        if ($function === 'compact') {
            foreach ($this->file->getChildren($arguments) as $argument) {
                $value = $this->file->getChildren($argument)[0] ?? null;
                $expression = $value === null ? null : $this->file->getChildren($value)[0] ?? null;
                foreach ($expression === null ? [] : $this->names($expression) as $name) {
                    $this->lookup($start, $name)?->read($start);
                }
            }

            return;
        }

        // No arrow function has a parameter with an empty name.
        $scope = $function === 'get_defined_vars' ? $this->scopes->lookup($start, name: '') : null;
        foreach ($scope === null ? [] : $this->variables[$scope] ?? [] as $variable) {
            if ($variable->first() >= $start) {
                continue;
            }

            $variable->read($start);
        }
    }

    /**
     * The variable names in a compact() argument: a string, or an array of
     * them.
     *
     * @return list<string>
     */
    private function names(Node $expression): array
    {
        $value = $this->file->getChildren($expression)[0] ?? null;
        if ($value === null) {
            return [];
        }

        if ($value->kind === NodeKind::Literal) {
            $text = $this->file->getText($value);
            $name = substr($text, offset: 1, length: -1);
            $quoted = $text[0] === "'" || $text[0] === '"';

            return (
                $quoted && preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name) === 1 ? ['$' . $name] : []
            );
        }

        if ($value->kind !== NodeKind::Array && $value->kind !== NodeKind::LegacyArray) {
            return [];
        }

        $names = [];
        foreach ($this->file->getChildren($value) as $element) {
            $item = $this->file->getChildren($element)[0] ?? null;
            $inner = $item?->kind === NodeKind::ValueArrayElement ? $this->file->getChildren($item)[0] ?? null : null;
            foreach ($inner === null ? [] : $this->names($inner) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Returns the variable of the scope that holds a variable node, or null
     * when the node is in no scope, or the name is a parameter of an arrow
     * function around it. The variables must come in source order.
     *
     * @param bool $outside Looks in the scope around the innermost function.
     */
    private function find(Node $variable, string $name, bool $outside = false): ?ScopeVariable
    {
        $start = $variable->span->start;
        $scope = $this->scopes->at($start, $name, $outside);

        return $scope === null ? null : ($this->variables[$scope][$name] ??= new ScopeVariable($start));
    }

    /**
     * Returns the variable of the scope that holds an offset, as find() does,
     * at any offset.
     */
    private function lookup(int $offset, string $name): ?ScopeVariable
    {
        $scope = $this->scopes->lookup($offset, $name);

        return $scope === null ? null : ($this->variables[$scope][$name] ??= new ScopeVariable($offset));
    }
}
