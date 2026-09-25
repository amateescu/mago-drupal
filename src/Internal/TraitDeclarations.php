<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\FunctionLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;

use function array_values;
use function strtolower;

/**
 * The declarations a method has in the classes using a trait.
 *
 * @internal
 */
final class TraitDeclarations
{
    private function __construct() {}

    /**
     * The distinct declarations the root classes get the method from, or null
     * when there are no roots or one of them lacks the method.
     *
     * A subclass of a root has the method too, and PHP keeps an override's
     * return type within the one it overrides, so the roots' declarations
     * answer for every class using the trait.
     *
     * @param list<string> $roots See TraitRoots.
     *
     * @return non-empty-list<FunctionLikeMetadata>|null
     */
    public static function of(Codebase $codebase, array $roots, string $method): ?array
    {
        $members = [];
        foreach ($roots as $root) {
            $members[] = new MemberIdentifier($root, $method);
        }

        $methods = [];
        foreach ($codebase->getMultipleDeclaringMethods($members) as $found) {
            if ($found === null) {
                return null;
            }

            $methods[strtolower($found->identifier->class ?? '')] ??= $found;
        }

        return $methods === [] ? null : array_values($methods);
    }
}
