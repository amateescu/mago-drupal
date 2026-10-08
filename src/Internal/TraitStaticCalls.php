<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_pop;
use function count;
use function in_array;
use function strtolower;
use function trim;

/**
 * Finds the calls in a trait that run without `$this`.
 *
 * @internal
 */
final class TraitStaticCalls
{
    private function __construct() {}

    /**
     * The `self::name()` and `static::name()` calls in the trait's static
     * methods, closures inside them included, as method name and call node.
     * Calls inside an anonymous class are left out, since `self` names that
     * class there.
     *
     * @return list<array{string, Node}>
     */
    public static function in(SourceFile $file, Node $trait): array
    {
        $calls = [];
        // Each node with whether it sits in a static method.
        $stack = [[$trait, false]];
        while (($entry = array_pop($stack)) !== null) {
            [$node, $static] = $entry;
            $static = $node->kind === NodeKind::Method ? self::isStatic($file, $node) : $static;
            $name = $static && $node->kind === NodeKind::StaticMethodCall ? self::selfCall($file, $node) : null;
            if ($name !== null) {
                $calls[] = [$name, $node];
            }

            foreach ($file->getChildren($node) as $child) {
                if ($child->kind === NodeKind::AnonymousClass) {
                    continue;
                }

                $stack[] = [$child, $static];
            }
        }

        return $calls;
    }

    private static function isStatic(SourceFile $file, Node $method): bool
    {
        foreach ($file->getChildren($method) as $child) {
            if ($child->kind === NodeKind::Modifier && strtolower(trim($file->getText($child))) === 'static') {
                return true;
            }
        }

        return false;
    }

    /**
     * The method name of a `self::name()` or `static::name()` call, or null
     * for any other class or a dynamic name.
     */
    private static function selfCall(SourceFile $file, Node $call): ?string
    {
        $children = $file->getChildren($call);
        if (
            count($children) < 2
            || !in_array(strtolower(trim($file->getText($children[0]))), ['self', 'static'], strict: true)
        ) {
            return null;
        }

        $selector = $file->getChildren($children[1])[0] ?? null;

        return $selector?->kind === NodeKind::LocalIdentifier ? $file->getText($selector) : null;
    }
}
