<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;

use function array_key_exists;
use function array_map;
use function in_array;
use function strtolower;

/**
 * The classes using a trait that do not get it from another class using it.
 *
 * A subclass has every method and property its parent has, so what holds for
 * these roots holds for every class using the trait. A test trait has
 * thousands of users behind a handful of base classes, and only the ancestor
 * names of each are fetched to find those bases. The roots are kept per trait
 * for one analysis.
 *
 * @internal
 */
final class TraitRoots
{
    /**
     * @var AnalysisMemo<list<string>>
     */
    private readonly AnalysisMemo $roots;

    public function __construct()
    {
        $this->roots = new AnalysisMemo();
    }

    /**
     * The root classes by name, or none when the trait is no trait or no
     * class uses it.
     *
     * @return list<string>
     */
    public function of(Codebase $codebase, string $trait): array
    {
        return $this->roots->get($codebase, strtolower($trait), static fn(): array => self::find($codebase, $trait));
    }

    /**
     * Whether some class uses the trait and every one of them implements the
     * interface.
     */
    public function allImplement(Codebase $codebase, string $trait, string $interface): bool
    {
        $roots = $this->of($codebase, $trait);
        $interface = strtolower($interface);
        foreach ($roots === [] ? [] : $codebase->getMultipleClassAncestors($roots) as $ancestors) {
            if (!in_array($interface, array_map(strtolower(...), $ancestors), strict: true)) {
                return false;
            }
        }

        return $roots !== [];
    }

    /**
     * @return list<string>
     */
    private static function find(Codebase $codebase, string $trait): array
    {
        $classes = TraitUsers::classes($codebase, $trait);
        $users = [];
        foreach ($classes as $class) {
            $users[strtolower($class)] = true;
        }

        $roots = [];
        foreach ($codebase->getMultipleClassAncestors($classes) as $index => $ancestors) {
            if (self::anyUser($ancestors, $users)) {
                continue;
            }

            $roots[] = $classes[$index];
        }

        return $roots;
    }

    /**
     * @param list<string> $ancestors
     * @param array<string, true> $users
     */
    private static function anyUser(array $ancestors, array $users): bool
    {
        foreach ($ancestors as $ancestor) {
            if (array_key_exists(strtolower($ancestor), $users)) {
                return true;
            }
        }

        return false;
    }
}
