<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_intersect_assoc;
use function array_key_exists;
use function array_slice;
use function count;
use function explode;
use function in_array;
use function is_array;
use function ltrim;
use function str_ends_with;
use function str_replace;
use function strtolower;
use function trim;
use function ucwords;

/**
 * Reads service registrations out of `*ServiceProvider.php` classes.
 *
 * A provider's `register()` adds services in PHP instead of YAML. The shapes
 * core and contrib write are `$container->register('id', Foo::class)`,
 * `->register('id')->setClass(Foo::class)`, `->setDefinition('id', new
 * Definition(Foo::class))` and `->setAlias('alias', 'id')`. A computed id is
 * skipped; a literal id with a computed class is kept without a class, so the
 * id is known even when its type is not. The result uses the same raw
 * definition shape as the YAML loader so both feed one index.
 *
 * Drupal's container builder makes what `register()` and `setAlias()` create
 * public. A `new Definition()` handed to `setDefinition()` is private since
 * Symfony 5.2, unless `setPublic(TRUE)` is called on it, so its service is
 * private unless the provider does that where the scan can see it: on the
 * chain that builds the definition, on the one `setDefinition()` returns, or
 * on the variable that holds the definition in the same function. A literal
 * `setPublic(FALSE)` makes any of them private. A `new ChildDefinition()`
 * takes its parent's class and visibility, and a definition built anywhere
 * else counts as public, so it is never reported.
 *
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 * @phpstan-type Built array{class?: non-empty-string, parent?: non-empty-string, public?: bool}
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 * @mago-expect lint:too-many-methods
 */
final class ServiceProviders
{
    private const DEFINITION = 'symfony\component\dependencyinjection\definition';

    private const CHILD_DEFINITION = 'symfony\component\dependencyinjection\childdefinition';

    private const CORE_PROVIDER = 'core/lib/Drupal/Core/CoreServiceProvider.php';

    /**
     * Lowercased names of the container methods that add a service.
     */
    private const REGISTRATIONS = ['register', 'setdefinition', 'setalias'];

    /**
     * Nodes a local variable can be declared in.
     */
    private const FUNCTION_LIKE = [NodeKind::Method, NodeKind::Function, NodeKind::Closure, NodeKind::ArrowFunction];

    private function __construct() {}

    /**
     * Whether Drupal registers the provider in this file: core's own, or a
     * module's `src/<Module>ServiceProvider.php` outside a tests directory. A
     * workspace that is the module itself names the file `src/…`, without
     * the module directory, so any provider there counts.
     */
    public static function discovered(string $path): bool
    {
        if (TestFiles::isTest($path)) {
            return false;
        }

        if (str_ends_with($path, self::CORE_PROVIDER)) {
            return true;
        }

        $parts = explode('/', $path);
        $count = count($parts);
        if ($count < 2 || $parts[$count - 2] !== 'src') {
            return false;
        }

        if ($count === 2) {
            return true;
        }

        $words = ucwords(str_replace(search: '_', replace: ' ', subject: $parts[$count - 3]));
        $camelized = str_replace(search: ' ', replace: '', subject: $words);

        return $parts[$count - 1] === $camelized . 'ServiceProvider.php';
    }

    /**
     * @return array<non-empty-string, Definition>
     */
    public static function definitions(SourceFile $file): array
    {
        $definitions = [];
        $visibility = [];
        foreach ($file->getNodes(NodeKind::MethodCall) as $call) {
            $invocation = Invocation::fromNode($file, $call);
            if ($invocation === null) {
                continue;
            }

            $name = strtolower($invocation->name);
            if ($name === 'getdefinition' || $name === 'finddefinition') {
                $change = self::fromGetDefinition($file, $call, $invocation);
                if ($change !== null) {
                    $visibility[$change[0]] = $change[1];
                }

                continue;
            }

            if (in_array($name, self::REGISTRATIONS, strict: true) && self::inAlter($file, $call)) {
                continue;
            }

            $entry = match ($name) {
                'register' => self::fromRegister($file, $call, $invocation),
                'setdefinition' => self::fromSetDefinition($file, $call, $invocation),
                'setalias' => self::fromSetAlias($file, $call, $invocation),
                default => null,
            };

            // A registration whose class is computed still declares the id,
            // but must not erase a class another call on the same id named.
            if ($entry !== null) {
                $definitions = ServiceDefinitions::merge($definitions, [$entry[0] => $entry[1]]);
            }
        }

        return self::withVisibility($definitions, $visibility);
    }

    /**
     * Whether the call sits in a provider's `alter()`, which changes the
     * services every provider registered. Those changes are not indexed.
     */
    private static function inAlter(SourceFile $file, Node $call): bool
    {
        foreach ($file->getAncestors($call) as $ancestor) {
            if ($ancestor->kind === NodeKind::Method) {
                return strtolower((string) Nodes::declaredName($file, $ancestor)) === 'alter';
            }
        }

        return false;
    }

    /**
     * `$container->getDefinition('id')`, or `findDefinition()`, as the
     * visibility it leaves the service with: what a `setPublic()` chained on
     * it says, public when the definition goes anywhere the scan cannot
     * follow, and null when it is dropped unchanged.
     *
     * @return array{non-empty-string, bool}|null
     */
    private static function fromGetDefinition(SourceFile $file, Node $call, Invocation $invocation): ?array
    {
        $id = self::literal($file, $invocation->argument(0));
        if ($id === null) {
            return null;
        }

        $public = self::isPublic($file, CallChains::after($file, $call), null);
        if ($public === null) {
            return CallChains::dropped($file, $call) ? null : [$id, true];
        }

        return [$id, $public];
    }

    /**
     * The definitions after the visibility the provider gives them through
     * `getDefinition()` elsewhere in the file, such as in `alter()`.
     *
     * @param array<non-empty-string, Definition> $definitions
     * @param array<non-empty-string, bool> $visibility
     * @return array<non-empty-string, Definition>
     */
    private static function withVisibility(array $definitions, array $visibility): array
    {
        foreach ($visibility as $id => $public) {
            $definition = $definitions[$id] ?? null;
            if (!is_array($definition)) {
                continue;
            }

            unset($definition['public']);
            $definitions[$id] = $public ? $definition : [...$definition, 'public' => false];
        }

        return $definitions;
    }

    /**
     * `$container->register('id', Foo::class)`, and a `setClass()` or
     * `setPublic()` chained on it.
     *
     * @return array{non-empty-string, Definition}|null
     */
    private static function fromRegister(SourceFile $file, Node $call, Invocation $invocation): ?array
    {
        $id = self::literal($file, $invocation->argument(0));
        if ($id === null) {
            return null;
        }

        $chain = CallChains::after($file, $call);
        $class = self::chainedClass($file, $chain) ?? self::className($file, $invocation->argument(1));
        $definition = $class === null ? [] : ['class' => $class];

        return [$id, self::isPublic($file, $chain, public: true) ? $definition : [...$definition, 'public' => false]];
    }

    /**
     * `$container->setDefinition('id', new Definition(Foo::class))`.
     *
     * @return array{non-empty-string, Definition}|null
     */
    private static function fromSetDefinition(SourceFile $file, Node $call, Invocation $invocation): ?array
    {
        $id = self::literal($file, $invocation->argument(0));
        $argument = $invocation->argument(1);
        if ($id === null) {
            return null;
        }

        $built = $argument === null ? null : self::built($file, $argument);
        if ($built === null) {
            return [$id, []];
        }

        [$definition, $calls] = $built;
        // A plain definition starts private; a child starts with no
        // visibility of its own and takes its parent's. The definition
        // `setDefinition()` returns can be made public by code the scan does
        // not follow once it goes anywhere but a chain in a statement.
        $public = self::isPublic($file, [...$calls, ...CallChains::after($file, $call)], $definition['public'] ?? null);
        $public = $public === false && !CallChains::dropped($file, $call) ? true : $public;
        unset($definition['public']);
        // The index takes a service as public unless it or its parent says
        // otherwise, and an entry without a class keeps the class another
        // source names only while it is empty.
        if ($public === false || $public === true && array_key_exists('parent', $definition)) {
            $definition['public'] = $public;
        }

        return [$id, $definition];
    }

    /**
     * `$container->setAlias('alias', 'id')`, public unless a literal
     * `setPublic(FALSE)` is chained on it.
     *
     * @return array{non-empty-string, Definition}|null
     */
    private static function fromSetAlias(SourceFile $file, Node $call, Invocation $invocation): ?array
    {
        $alias = self::literal($file, $invocation->argument(0));
        $target = self::literal($file, $invocation->argument(1));
        if ($alias === null || $target === null) {
            return null;
        }

        return [
            $alias,
            self::isPublic($file, CallChains::after($file, $call), public: true)
                ? '@' . $target
                : ['alias' => $target, 'public' => false],
        ];
    }

    /**
     * The definition handed to `setDefinition()` and the method calls made
     * on it, or null when the scan cannot see where it was built.
     *
     * @return array{Built, list<Invocation>}|null
     */
    private static function built(SourceFile $file, Node $argument): ?array
    {
        [$start, $calls] = CallChains::from($file, $argument);
        if ($start->kind === NodeKind::Instantiation) {
            $definition = self::instantiated($file, $start, $calls);

            return $definition === null ? null : [$definition, $calls];
        }

        // The calls on the variable here are among the ones held() finds.
        $variable = $start->kind === NodeKind::Variable ? $file->getChildren($start)[0] ?? null : null;

        return $variable?->kind === NodeKind::DirectVariable ? self::held($file, $variable) : null;
    }

    /**
     * What a variable holds when every assignment to it in the function puts
     * a new definition in it, and nothing but method calls and
     * `setDefinition()` calls reads it. The calls come in source order, and
     * the scan does not follow the flow between them, so several assignments
     * keep only what their definitions share, and a `setClass()` on the
     * variable counts only after a single one.
     *
     * @return array{Built, list<Invocation>}|null
     */
    private static function held(SourceFile $file, Node $variable): ?array
    {
        $scope = $file->getParent($variable);
        while ($scope !== null && !in_array($scope->kind, self::FUNCTION_LIKE, strict: true)) {
            $scope = $file->getParent($scope);
        }

        $name = $file->getText($variable);
        $definitions = [];
        $calls = [];
        foreach ($scope === null ? [] : $file->getDescendants($scope, NodeKind::DirectVariable) as $occurrence) {
            if ($file->getText($occurrence) !== $name) {
                continue;
            }

            $use = self::use($file, $occurrence);
            $found = $use === null ? null : self::usedAs($file, $use);
            if ($found === null) {
                return null;
            }

            [$definition, $chain] = $found;
            $definitions = $definition === null ? $definitions : [...$definitions, $definition];
            $calls = [...$calls, ...$chain];
        }

        [$first, $others] = [$definitions[0] ?? null, array_slice($definitions, offset: 1)];
        if ($first === null || $others !== []) {
            /** @var Built $shared */
            $shared = $first === null ? [] : array_intersect_assoc($first, ...$others);

            return $first === null ? null : [$shared, $calls];
        }

        $class = self::chainedClass($file, $calls);

        return [$class === null ? $first : [...$first, 'class' => $class], $calls];
    }

    /**
     * What one use of the variable shows: the definition an assignment puts
     * in it, or the method calls made on it. A `setDefinition()` argument
     * shows nothing. Null for an assignment of anything but a new
     * definition.
     *
     * @return array{Built|null, list<Invocation>}|null
     */
    private static function usedAs(SourceFile $file, Node $use): ?array
    {
        $invocation = $use->kind === NodeKind::MethodCall ? Invocation::fromNode($file, $use) : null;
        if ($invocation !== null) {
            return [null, [$invocation, ...CallChains::after($file, $use)]];
        }

        $value = $use->kind === NodeKind::Assignment ? $file->getChildren($use)[2] ?? null : null;
        if ($value === null) {
            return [null, []];
        }

        [$start, $chain] = CallChains::from($file, $value);
        $definition = $start->kind === NodeKind::Instantiation ? self::instantiated($file, $start, $chain) : null;

        return $definition === null ? null : [$definition, $chain];
    }

    /**
     * The node that uses a variable occurrence when the use leaves the
     * definition in plain sight: the assignment it is the target of, the
     * method call it is the receiver of, or the argument of a
     * `setDefinition()` call it is the second argument of. Null for any
     * other use, such as a parameter, an argument to another call or a
     * closure's `use`.
     */
    private static function use(SourceFile $file, Node $occurrence): ?Node
    {
        $holder = $file->getParent($occurrence);
        $expression = $holder?->kind === NodeKind::Variable ? $file->getParent($holder) : null;
        $user = $expression === null ? null : $file->getParent($expression);
        if ($expression === null || $user === null || $expression->kind !== NodeKind::Expression) {
            return null;
        }

        $first = $file->getChildren($user)[0] ?? null;
        if (in_array($user->kind, [NodeKind::Assignment, NodeKind::MethodCall], strict: true)) {
            return $first?->id === $expression->id ? $user : null;
        }

        // An argument sits in `PositionalArgument -> Argument -> ArgumentList`.
        $argument = $user->kind === NodeKind::PositionalArgument ? $file->getParent($user) : null;
        $list = $argument === null ? null : $file->getParent($argument);
        $call = $list === null ? null : $file->getParent($list);
        $invocation = $call?->kind === NodeKind::MethodCall ? Invocation::fromNode($file, $call) : null;
        if ($call === null || $invocation === null || strtolower($invocation->name) !== 'setdefinition') {
            return null;
        }

        return $invocation->argument(1)?->id === CallChains::unwrap($file, $expression)->id ? $user : null;
    }

    /**
     * The raw definition a `new Definition(Foo::class)` or `new
     * ChildDefinition('parent')` starts, with the class a chained
     * `setClass()` names. A plain definition starts private. Null for any
     * other class, and for a child of a computed parent.
     *
     * @param list<Invocation> $calls
     * @return Built|null
     */
    private static function instantiated(SourceFile $file, Node $instantiation, array $calls): ?array
    {
        $callee = $file->getChildren($instantiation)[1] ?? null;
        $identifier = $callee === null ? null : $file->getFirstDescendant($callee, NodeKind::Identifier);
        $name = $identifier === null ? null : Nodes::resolved($file, $identifier);
        $first = Instantiations::arguments($file, $instantiation)[0] ?? null;
        $class = self::chainedClass($file, $calls);
        $parent = self::literal($file, $first);

        return match ($name === null ? null : strtolower($name)) {
            self::DEFINITION => [...self::withClass($class ?? self::className($file, $first)), 'public' => false],
            self::CHILD_DEFINITION => $parent === null ? null : ['parent' => $parent, ...self::withClass($class)],
            default => null,
        };
    }

    /**
     * @param non-empty-string|null $class
     * @return array{class?: non-empty-string}
     */
    private static function withClass(?string $class): array
    {
        return $class === null ? [] : ['class' => $class];
    }

    /**
     * The class the last `setClass()` of the calls names.
     *
     * @param list<Invocation> $calls
     * @return non-empty-string|null
     */
    private static function chainedClass(SourceFile $file, array $calls): ?string
    {
        $class = null;
        foreach ($calls as $invocation) {
            if (strtolower($invocation->name) !== 'setclass') {
                continue;
            }

            $class = self::className($file, $invocation->argument(0));
        }

        return $class;
    }

    /**
     * The visibility after the calls, starting from the given one. The last
     * `setPublic()` decides, and one with a computed argument makes the
     * definition public, the side that never reports anything.
     *
     * @param list<Invocation> $calls
     * @return ($public is bool ? bool : bool|null)
     */
    private static function isPublic(SourceFile $file, array $calls, ?bool $public): ?bool
    {
        foreach ($calls as $invocation) {
            if (strtolower($invocation->name) !== 'setpublic') {
                continue;
            }

            $argument = $invocation->argument(0);
            $value = $argument === null ? '' : trim($file->getText(CallChains::unwrap($file, $argument)));
            $public = strtolower(ltrim($value, characters: '\\')) !== 'false';
        }

        return $public;
    }

    /**
     * Reads a class name off `Foo::class` or a string literal.
     *
     * @return non-empty-string|null
     */
    private static function className(SourceFile $file, ?Node $node): ?string
    {
        $node = self::unwrapped($file, $node);
        if ($node === null) {
            return null;
        }

        // `Foo::class` arrives as `Access -> ClassConstantAccess`.
        if ($node->kind === NodeKind::Access) {
            $node = $file->getChildren($node)[0] ?? $node;
        }

        if ($node->kind !== NodeKind::ClassConstantAccess) {
            $literal = Values::literalString($file, $node);

            return $literal === null ? null : Shape::nonEmptyString(ltrim($literal, characters: '\\'));
        }

        $selector = $file->getChildren($node)[1] ?? null;
        if ($selector === null || strtolower($file->getText($selector)) !== 'class') {
            return null;
        }

        $resolved = $file->getResolvedName($node);

        return $resolved === null ? null : Shape::nonEmptyString(ltrim($resolved->name, characters: '\\'));
    }

    /**
     * Reads a non-empty string literal, or null for anything else.
     *
     * @return non-empty-string|null
     */
    private static function literal(SourceFile $file, ?Node $node): ?string
    {
        $node = self::unwrapped($file, $node);

        return $node === null ? null : Shape::nonEmptyString(Values::literalString($file, $node));
    }

    private static function unwrapped(SourceFile $file, ?Node $node): ?Node
    {
        return $node === null ? null : CallChains::unwrap($file, $node);
    }
}
