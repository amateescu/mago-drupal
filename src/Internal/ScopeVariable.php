<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;

use function min;

/**
 * What one scope does with one variable name: where it writes the name,
 * whether it reads it, the cases that make the name count as used, and
 * where it declares the name again.
 *
 * @mago-expect lint:too-many-methods
 * @mago-expect lint:too-many-properties
 *
 * @internal
 */
final class ScopeVariable
{
    /**
     * A plain variable.
     */
    public const LOCAL = 'variable';

    /**
     * A variable declared with `static`.
     */
    public const STATIC = 'static variable';

    /**
     * A variable declared with `global`.
     */
    public const GLOBAL = 'global variable';

    /**
     * A variable of a closure's `use` list.
     */
    public const BOUND = 'bound variable';

    /**
     * A parameter. Only the redeclaration check names it.
     */
    public const PARAMETER = 'function parameter';

    /**
     * @var list<Span>
     */
    private array $writes = [];

    private bool $read = false;

    private bool $allowed = false;

    /**
     * Set by a write that gives the name a value: an assignment, a list
     * element, a foreach variable, a catch variable or a `use` binding. A
     * `= &` binding and a `static` or `global` declaration without a value
     * don't set it.
     */
    private bool $initialized = false;

    /**
     * Set by a write such as `$x = 1` or `$x .= 'a'`.
     */
    private bool $assigned = false;

    private bool $referenceBound = false;

    private bool $foreachReference = false;

    private bool $global = false;

    /**
     * @var self::LOCAL|self::STATIC|self::GLOBAL|self::BOUND
     */
    private string $kind = self::LOCAL;

    /**
     * What the name was declared as, which VariableAnalysis calls its scope
     * type. A declaration sets it. A write sets it to a plain variable when
     * it isn't set yet. A read leaves it alone.
     *
     * @var null|self::LOCAL|self::STATIC|self::GLOBAL|self::BOUND|self::PARAMETER
     */
    private ?string $declared = null;

    /**
     * The declarations that come after another one, with what the name was
     * declared as before and after.
     *
     * @var list<array{Span, string, string}>
     */
    private array $redeclarations = [];

    private int $first;

    public function __construct(int $offset)
    {
        $this->first = $offset;
    }

    /**
     * Marks the name as read.
     */
    public function read(int $offset): void
    {
        $this->read = true;
        $this->first = min($this->first, $offset);
    }

    /**
     * Records the value of a `$key => $value` foreach, which may stay
     * unused for the whole scope.
     */
    public function allow(): void
    {
        $this->allowed = true;
        $this->declared ??= self::LOCAL;
    }

    /**
     * Records a parameter, which counts as used.
     */
    public function parameter(Span $span): void
    {
        $this->allowed = true;
        $this->redeclare($span, self::PARAMETER);
    }

    /**
     * Records a catch variable, which counts as used.
     */
    public function catchVariable(Span $span): void
    {
        $this->allowed = true;
        $this->redeclare($span, self::LOCAL);
    }

    /**
     * Records a write that gives the name a value, such as a foreach
     * variable or a list element.
     */
    public function write(Span $span): void
    {
        $this->writes[] = $span;
        $this->initialized = true;
        $this->first = min($this->first, $span->start);
        $this->declared ??= self::LOCAL;
    }

    /**
     * Records the name as the left side of an assignment.
     */
    public function assign(Span $span): void
    {
        $this->write($span);
        $this->assigned = true;
    }

    /**
     * Records a `$x = &$y` binding.
     */
    public function bindReference(Span $span): void
    {
        $this->declare($span, self::LOCAL);
        $this->referenceBound = true;
    }

    /**
     * Records a variable of a closure's `use` list bound by reference, as
     * the closure sees it. It counts as used.
     */
    public function bindByReference(Span $span): void
    {
        $this->read($span->start);
        $this->redeclare($span, self::BOUND);
    }

    /**
     * Records a declaration: `static $x;`, `global $x;`, a variable of a
     * closure's `use` list as the closure sees it, or a `= &` binding. The
     * last one sets the kind of the name in the message. A `use` binding
     * gives the name a value.
     *
     * @param self::LOCAL|self::STATIC|self::GLOBAL|self::BOUND $kind
     */
    public function declare(Span $span, string $kind): void
    {
        $this->writes[] = $span;
        $this->kind = $kind;
        $this->global = $this->global || $kind === self::GLOBAL;
        $this->initialized = $this->initialized || $kind === self::BOUND;
        $this->first = min($this->first, $span->start);
        $this->redeclare($span, $kind);
    }

    /**
     * Records `foreach ($items as &$x)`.
     */
    public function foreachReference(Span $span): void
    {
        $this->write($span);
        $this->foreachReference = true;
    }

    /**
     * The offset of the first write or read of the name.
     */
    public function first(): int
    {
        return $this->first;
    }

    /**
     * The kind of the name, which the message names.
     *
     * @return self::LOCAL|self::STATIC|self::GLOBAL|self::BOUND
     */
    public function kind(): string
    {
        return $this->kind;
    }

    /**
     * The writes to report, or none when the name counts as used.
     *
     * @return list<Span>
     */
    public function unusedWrites(): array
    {
        return $this->read || $this->allowed || $this->isWrittenThrough() ? [] : $this->writes;
    }

    /**
     * The declarations that come after another one in source order, each
     * with what the name was declared as before it and what it declares.
     *
     * @return list<array{Span, string, string}>
     */
    public function redeclarations(): array
    {
        return $this->redeclarations;
    }

    /**
     * Records what a declaration declares the name as, and a redeclaration
     * when the name was declared before. VariableAnalysis compares by
     * source position, not by flow, so a declaration in another branch
     * counts.
     *
     * @param self::LOCAL|self::STATIC|self::GLOBAL|self::BOUND|self::PARAMETER $kind
     */
    private function redeclare(Span $span, string $kind): void
    {
        // A plain variable is declared only by a catch variable or a `= &`
        // binding, and VariableAnalysis lets those two take the name of a
        // plain variable.
        if ($this->declared !== null && ($kind !== self::LOCAL || $this->declared !== self::LOCAL)) {
            $this->redeclarations[] = [$span, $this->declared, $kind];
        }

        $this->declared = $kind;
    }

    /**
     * Whether a write counts as a use. A reference passes its writes on to
     * the variable that it points to. A `= &` binding counts as used once
     * something writes through it, and a foreach reference once an
     * assignment does. A global counts as used once the function writes it.
     */
    private function isWrittenThrough(): bool
    {
        return (
            ($this->referenceBound || $this->global)
            && $this->initialized
            || $this->foreachReference && $this->assigned
        );
    }
}
