<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;

use function strtolower;

/**
 * The class-likes that use a trait.
 *
 * Mago's descendant lists follow `extends` and `implements`, not `use`, so
 * the users come from method searches instead.
 *
 * @internal
 */
final class TraitUsers
{
    private function __construct() {}

    /**
     * The class-likes whose visible method of that name the trait declares,
     * from a search by that name across the codebase.
     *
     * @return list<ClassLikeMetadata|null>
     */
    public static function of(Codebase $codebase, string $trait, string $method): array
    {
        $users = self::names($codebase, $trait, $method);

        return $users === [] ? [] : $codebase->getMultipleClassLikes($users);
    }

    /**
     * The names of the classes using the trait, or none when it is no trait or
     * declares no method to find them by.
     *
     * Mago counts a class that uses the trait, directly, through another
     * trait or through a parent, as a descendant of it, so a search among
     * those descendants for one of the trait's methods names each of them
     * once. The traits among them are left out. Only names are fetched,
     * since a test trait has thousands of users and decoding their metadata
     * is slow.
     *
     * @return list<string>
     */
    public static function classes(Codebase $codebase, string $trait): array
    {
        $method = $codebase->getTrait($trait)->methods[0] ?? null;
        if ($method === null) {
            return [];
        }

        $users = [];
        foreach ($codebase->findMethods(descendantsOf: $trait, name: $method, fields: 0) as $visible) {
            $users[] = $visible->method->class;
        }

        $classes = [];
        foreach ($codebase->checkMultipleTraitsExist($users) as $index => $isTrait) {
            if ($isTrait) {
                continue;
            }

            $classes[] = $users[$index];
        }

        return $classes;
    }

    /**
     * The class-likes whose visible method of that name the trait declares.
     *
     * @return list<string>
     */
    private static function names(Codebase $codebase, string $trait, string $method): array
    {
        $trait = strtolower($trait);
        $users = [];
        foreach ($codebase->findMethods(name: $method, fields: 0) as $visible) {
            $class = $visible->method->class;
            if (strtolower($visible->identifier->class ?? '') === $trait && strtolower($class) !== $trait) {
                $users[] = $class;
            }
        }

        return $users;
    }
}
