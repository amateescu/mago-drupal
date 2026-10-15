<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;
use function array_pop;
use function count;
use function in_array;

/**
 * Finds the variable scope that holds an offset: a function, a method, a
 * closure, or the top level of the file.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 *
 * @internal
 */
final class FunctionScopes
{
    /**
     * The functions, arrow functions and class-likes. A class body is in no
     * scope, except for its methods.
     */
    public const REGIONS = [
        NodeKind::Function,
        NodeKind::Method,
        NodeKind::Closure,
        NodeKind::ArrowFunction,
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    /**
     * The nodes that own a scope.
     */
    private const FUNCTIONS = [NodeKind::Function, NodeKind::Method, NodeKind::Closure];

    /**
     * The region nodes of the file, in source order, which is the order of
     * the target nodes.
     *
     * @var list<Node>
     */
    private readonly array $regions;

    /**
     * The index of the first region that the sweep hasn't reached.
     */
    private int $next = 0;

    /**
     * The regions around the sweep's offset, outermost first.
     *
     * @var list<Node>
     */
    private array $open = [];

    /**
     * Where the body of each anonymous class starts. Its arguments, as in
     * `new class($x) {}`, are before it.
     *
     * @var array<int, int>
     */
    private array $bodyStarts = [];

    /**
     * The parameter names of each arrow function read so far.
     *
     * @var array<int, array<string, true>>
     */
    private array $arrowParameters = [];

    /**
     * @param non-negative-int $program The id of the file's Program node,
     *   which is the scope of the top level.
     */
    public function __construct(
        private readonly SourceFile $file,
        private readonly int $program,
    ) {
        // The rule that calls this targets the kinds in REGIONS, so they are
        // in the target nodes. One pass over them next to the variables costs
        // far less than a walk up the parents of each variable.
        $regions = [];
        foreach ($file->getTargetNodes() as $node) {
            if (!in_array($node->kind, self::REGIONS, strict: true)) {
                continue;
            }

            $regions[] = $node;
        }

        $this->regions = $regions;
    }

    /**
     * Returns the id of the scope that holds the variable $name at an
     * offset, or null when the offset is in no scope, or $name is a
     * parameter of an arrow function around it.
     *
     * The offsets of successive calls must not decrease.
     *
     * @param bool $outside Skips the innermost function, as for a variable
     *   of a closure's `use` list, which reads the scope around the closure.
     */
    public function at(int $offset, string $name, bool $outside = false): ?int
    {
        while (($region = $this->regions[$this->next] ?? null) !== null && $region->span->start <= $offset) {
            ++$this->next;
            if ($region->span->end <= $offset) {
                continue;
            }

            $this->close($region->span->start);
            $this->open[] = $region;
        }

        $this->close($offset);

        return $this->resolve($this->open, $offset, $name, $outside);
    }

    /**
     * Returns the id of the scope that holds the variable $name at any
     * offset, as at() does, without moving the sweep.
     */
    public function lookup(int $offset, string $name): ?int
    {
        $open = [];
        foreach ($this->regions as $region) {
            if ($region->span->start > $offset) {
                break;
            }

            if ($region->span->end <= $offset) {
                continue;
            }

            $open[] = $region;
        }

        return $this->resolve($open, $offset, $name, outside: false);
    }

    /**
     * Drops the open regions that end at or before an offset.
     */
    private function close(int $offset): void
    {
        while ($this->open !== [] && $this->open[count($this->open) - 1]->span->end <= $offset) {
            array_pop($this->open);
        }
    }

    /**
     * Returns the scope of a variable from the regions around it.
     *
     * An arrow function has no scope of its own. Its body uses the enclosing
     * scope, except for the names of its parameters. A class body outside a
     * method, such as a property, is in no scope.
     *
     * @param list<Node> $open
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    private function resolve(array $open, int $offset, string $name, bool $outside): ?int
    {
        for ($index = count($open) - 1; $index >= 0; --$index) {
            $region = $open[$index];
            if ($region->kind === NodeKind::ArrowFunction) {
                if (array_key_exists($name, $this->arrowParameters($region))) {
                    return null;
                }

                continue;
            }

            if (in_array($region->kind, self::FUNCTIONS, strict: true)) {
                if ($outside) {
                    $outside = false;

                    continue;
                }

                return $region->id;
            }

            if ($region->kind !== NodeKind::AnonymousClass || $offset >= $this->bodyStart($region)) {
                return null;
            }
        }

        return $this->program;
    }

    /**
     * Where the body of an anonymous class starts: at its first member, or
     * at its end when it has none.
     */
    private function bodyStart(Node $class): int
    {
        if (array_key_exists($class->id, $this->bodyStarts)) {
            return $this->bodyStarts[$class->id];
        }

        $start = $class->span->end;
        foreach ($this->file->getChildren($class) as $child) {
            if ($child->kind !== NodeKind::ClassLikeMember) {
                continue;
            }

            $start = $child->span->start;

            break;
        }

        return $this->bodyStarts[$class->id] = $start;
    }

    /**
     * The parameter names of an arrow function.
     *
     * @return array<string, true>
     */
    private function arrowParameters(Node $arrow): array
    {
        if (array_key_exists($arrow->id, $this->arrowParameters)) {
            return $this->arrowParameters[$arrow->id];
        }

        $names = [];
        foreach ($this->file->getChildren($arrow) as $child) {
            if ($child->kind !== NodeKind::FunctionLikeParameterList) {
                continue;
            }

            foreach ($this->file->getChildren($child) as $parameter) {
                foreach ($this->file->getChildren($parameter) as $part) {
                    if ($part->kind !== NodeKind::DirectVariable) {
                        continue;
                    }

                    $names[$this->file->getText($part)] = true;
                }
            }
        }

        return $this->arrowParameters[$arrow->id] = $names;
    }
}
