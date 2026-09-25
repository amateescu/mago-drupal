<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function ltrim;
use function strtolower;

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
 * @internal
 *
 * @phpstan-import-type Definition from ServiceYaml
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ServiceProviders
{
    private function __construct() {}

    /**
     * @return array<non-empty-string, Definition>
     */
    public static function definitions(SourceFile $file): array
    {
        $definitions = [];
        foreach ($file->getNodes(NodeKind::MethodCall) as $call) {
            $invocation = Invocation::fromNode($file, $call);
            if ($invocation === null) {
                continue;
            }

            $entry = match (strtolower($invocation->name)) {
                'register' => self::fromRegister($file, $invocation),
                'setdefinition' => self::fromSetDefinition($file, $invocation),
                'setalias' => self::fromSetAlias($file, $invocation),
                'setclass' => self::fromSetClass($file, $call, $invocation),
                default => null,
            };

            // A registration whose class is computed still declares the id,
            // but must not erase a class another call on the same id named.
            if ($entry !== null) {
                $definitions = ServiceDefinitions::merge($definitions, [$entry[0] => $entry[1]]);
            }
        }

        return $definitions;
    }

    /**
     * `$container->register('id', Foo::class)`.
     *
     * @return array{non-empty-string, Definition}|null
     */
    private static function fromRegister(SourceFile $file, Invocation $invocation): ?array
    {
        $id = self::literal($file, $invocation->argument(0));
        if ($id === null) {
            return null;
        }

        $class = self::className($file, $invocation->argument(1));

        return [$id, $class === null ? [] : ['class' => $class]];
    }

    /**
     * `$container->setDefinition('id', new Definition(Foo::class))`.
     *
     * @return array{non-empty-string, Definition}|null
     */
    private static function fromSetDefinition(SourceFile $file, Invocation $invocation): ?array
    {
        $id = self::literal($file, $invocation->argument(0));
        if ($id === null) {
            return null;
        }

        $definition = self::unwrapped($file, $invocation->argument(1));
        $class =
            $definition === null || $definition->kind !== NodeKind::Instantiation
                ? null
                : self::className($file, Instantiations::arguments($file, $definition)[0] ?? null);

        return [$id, $class === null ? [] : ['class' => $class]];
    }

    /**
     * `$container->setAlias('alias', 'id')`.
     *
     * @return array{non-empty-string, Definition}|null
     */
    private static function fromSetAlias(SourceFile $file, Invocation $invocation): ?array
    {
        $alias = self::literal($file, $invocation->argument(0));
        $target = self::literal($file, $invocation->argument(1));

        return $alias === null || $target === null ? null : [$alias, '@' . $target];
    }

    /**
     * `$container->register('id')->setClass(Foo::class)`, where the id sits on
     * the receiver call.
     *
     * @return array{non-empty-string, Definition}|null
     */
    private static function fromSetClass(SourceFile $file, Node $call, Invocation $invocation): ?array
    {
        $receiver = self::unwrapped($file, $file->getChildren($call)[0] ?? null);
        if ($receiver === null || $receiver->kind !== NodeKind::MethodCall) {
            return null;
        }

        $register = Invocation::fromNode($file, $receiver);
        if ($register === null || strtolower($register->name) !== 'register') {
            return null;
        }

        $id = self::literal($file, $register->argument(0));
        $class = self::className($file, $invocation->argument(0));

        return $id === null || $class === null ? null : [$id, ['class' => $class]];
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
        return $node === null ? null : Values::unwrap($file, $node);
    }
}
