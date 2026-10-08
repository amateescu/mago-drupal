<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\CallbackUse;
use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\TraitComposers;
use amateescu\MagoDrupal\Internal\TrustedCallbackClasses;
use amateescu\MagoDrupal\Internal\TrustedCallbacks;
use Closure;
use Mago\Sdk\Analyzer\Metadata\MethodFields;

use function array_flip;
use function array_key_exists;
use function array_map;
use function implode;
use function in_array;
use function preg_quote;
use function strtolower;

/**
 * Reports an override that drops a parent method's `#[TrustedCallback]`;
 * runs on the descendants of the classes declaring such a method.
 *
 * Core reads the attribute by reflection off the method the class has, and
 * PHP attributes are not inherited, so the override is not trusted as a
 * render callback. A class implementing `RenderCallbackInterface`, or one
 * whose `trustedCallbacks()` lists the method or cannot be read, is left
 * alone.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class TrustedCallbackOverrideCheck implements MetadataCheck
{
    public const CODE = 'trusted-callback-override';

    /**
     * @var array<string, int>
     */
    private readonly array $methods;

    /**
     * A class has to declare a method of one of those names. Built once,
     * since the hook asks for it on every class.
     */
    private readonly string $gate;

    /**
     * @param list<string> $methods Lowercased names of the methods carrying
     *   the attribute under the Drupal root.
     */
    public function __construct(
        private readonly TrustedCallbacks $trusted,
        array $methods,
    ) {
        $this->methods = array_flip($methods);
        $names = array_map(static fn(string $name): string => preg_quote($name, delimiter: '/'), $methods);
        $this->gate = '/\bfunction\s++&?\s*+(?:' . implode('|', $names) . ')\s*+\(/i';
    }

    /**
     * The classes whose descendants the check runs on: the classes declaring
     * a method with the attribute, and the classes using a trait that does.
     * The host's descendant targets follow parents and interfaces, so a
     * trait's method reaches a subclass through the class using it.
     *
     * @param Closure(non-empty-string): TraitComposers $composers
     * @return list<string>
     */
    public static function ancestors(TrustedCallbackClasses $declared, Closure $composers): array
    {
        $ancestors = $declared->classes;
        foreach ($declared->traits as $trait) {
            foreach ($composers($trait)->classes as $class) {
                $ancestors[] = $class;
            }
        }

        return $ancestors;
    }

    public function textGate(): ?string
    {
        return $this->gate;
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $parent = $class->class->directParentClass;
        if ($parent === null || TrustedCallbacks::implements($class->class, CallbackUse::RENDER_CALLBACK)) {
            return;
        }

        foreach ($class->methods() as $method) {
            $name = $method->originalName ?? $method->name ?? $method->method->member;
            $location = $method->nameLocation ?? $method->location;
            $inherited = array_key_exists(strtolower($name), $this->methods) && !TrustedCallbacks::attributed($method)
                ? $class->codebase->findMethods(class: $parent, name: $name, fields: MethodFields::ATTRIBUTES)[0]
                ?? null
                : null;
            if (
                $location === null
                || $inherited === null
                || !TrustedCallbacks::attributed($inherited)
                || $this->listed($class, $name)
            ) {
                continue;
            }

            $declaring = $inherited->identifier->class ?? $parent;
            $declaring = $class->codebase->getClassLike($declaring)->originalName ?? $declaring;
            $reporter->warning(self::CODE, Reporter::issue(
                "{$class->name()}::{$name}() overrides {$declaring}::{$name}() without its #[TrustedCallback] attribute, so Drupal does not trust it as a render callback.",
                $location,
                'Add #[TrustedCallback] to the override. PHP does not inherit attributes, and core reads the attribute off the method the class has.',
                'https://www.drupal.org/node/3349470',
            ));
        }
    }

    /**
     * Whether the class's `trustedCallbacks()` lists the method, or cannot be
     * read, which leaves it quiet as well.
     */
    private function listed(ClassFacts $class, string $name): bool
    {
        if (!TrustedCallbacks::implements($class->class, TrustedCallbacks::INTERFACE)) {
            return false;
        }

        $listed = $this->trusted->listed($class->codebase, $class->class->name);

        return $listed === null || in_array($name, $listed, strict: true);
    }
}
