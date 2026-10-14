<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MethodFields;
use Mago\Sdk\Analyzer\Metadata\MethodMetadataProjection;

use function array_key_exists;
use function file_get_contents;
use function in_array;
use function is_file;
use function strtolower;
use function substr;

/**
 * Answers whether core's `DoTrustedCallbackTrait::doTrustedCallback()`
 * accepts a class method as a callback.
 *
 * Core trusts the method when the class implements the extra interface the
 * caller passes, when the class implements `TrustedCallbackInterface` and
 * `trustedCallbacks()` lists the method, or when the method as the class
 * sees it carries `#[TrustedCallback]`. Reflection reads the attribute off
 * the nearest declaration, so an override without it loses it.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class TrustedCallbacks
{
    public const INTERFACE = 'Drupal\Core\Security\TrustedCallbackInterface';

    public const ATTRIBUTE = 'Drupal\Core\Security\Attribute\TrustedCallback';

    /**
     * @var AnalysisMemo<list<string>|null>
     */
    private readonly AnalysisMemo $lists;

    public function __construct()
    {
        $this->lists = new AnalysisMemo();
    }

    /**
     * Whether core rejects the method as a callback on the class. A
     * `trustedCallbacks()` body that cannot be read counts as trusting it.
     *
     * @param MethodMetadataProjection|null $method The method as the class
     *   sees it, with its attributes.
     * @param string $name The method name as the callback writes it, since
     *   core compares it with the list strictly.
     * @param string|null $interface The extra interface the caller trusts.
     */
    public function rejects(
        Codebase $codebase,
        ClassLikeMetadata $class,
        ?MethodMetadataProjection $method,
        string $name,
        ?string $interface,
    ): bool {
        if (
            $interface !== null && self::implements($class, $interface)
            || $method !== null && self::attributed($method)
        ) {
            return false;
        }

        if (!self::implements($class, self::INTERFACE)) {
            return true;
        }

        $listed = $this->listed($codebase, $class->name);

        return $listed !== null && !in_array($name, $listed, strict: true);
    }

    /**
     * Whether every descendant of the class rejects the method as well, so
     * that a callback naming the class through `static::class` or `$this`
     * fails whatever subclass it runs on. A descendant whose hierarchy Mago
     * could not resolve may implement an interface it does not list, so it
     * counts as trusting the method.
     */
    public function rejectedByDescendants(
        Codebase $codebase,
        ClassLikeMetadata $class,
        string $name,
        ?string $interface,
    ): bool {
        $descendants = $codebase->getClassDescendants($class->name);
        if ($descendants === []) {
            return true;
        }

        $methods = [];
        foreach ($codebase->findMethods(
            descendantsOf: $class->name,
            name: $name,
            fields: MethodFields::ATTRIBUTES,
        ) as $found) {
            $methods[strtolower($found->method->class)] = $found;
        }

        foreach ($codebase->getMultipleClassLikes($descendants) as $descendant) {
            if ($descendant === null) {
                continue;
            }

            $method = $methods[strtolower($descendant->name)] ?? null;
            if (
                $descendant->hasIncompleteHierarchy()
                || !$this->rejects($codebase, $descendant, $method, $name, $interface)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the method's declaration carries `#[TrustedCallback]`.
     */
    public static function attributed(MethodMetadataProjection $method): bool
    {
        return Attributes::named($method->attributes ?? [], self::ATTRIBUTE) !== [];
    }

    public static function implements(ClassLikeMetadata $class, string $interface): bool
    {
        return in_array(strtolower($interface), $class->parentInterfaces, strict: true);
    }

    /**
     * The names the class's `trustedCallbacks()` returns, or null when its
     * body, or a parent body it merges, cannot be read.
     *
     * @return list<string>|null
     */
    public function listed(Codebase $codebase, string $class): ?array
    {
        return $this->merged($codebase, $class, []);
    }

    /**
     * The list, following `parent::trustedCallbacks()` through the classes
     * not visited yet. A class met twice has a broken hierarchy, and its
     * list counts as unreadable.
     *
     * @param array<string, true> $visited Lowercased names of the classes
     *   whose list is being read.
     * @return list<string>|null
     */
    private function merged(Codebase $codebase, string $class, array $visited): ?array
    {
        $key = strtolower($class);
        if (array_key_exists($key, $visited)) {
            return null;
        }

        $visited[$key] = true;

        return $this->lists->get($codebase, $key, fn(): ?array => $this->read($codebase, $class, $visited));
    }

    /**
     * @param array<string, true> $visited
     * @return list<string>|null
     */
    private function read(Codebase $codebase, string $class, array $visited): ?array
    {
        $method =
            $codebase->findMethods(class: $class, name: 'trustedCallbacks', fields: MethodFields::LOCATIONS)[0] ?? null;
        $location = $method?->location;
        $file = $location?->file;
        if ($method === null || $location === null || $file === null || !is_file($file)) {
            return null;
        }

        $list = TrustedCallbackList::parse(substr(
            (string) file_get_contents($file),
            $location->span->start,
            $location->span->length(),
        ));
        if ($list === null || !$list->parent) {
            return $list?->names;
        }

        // `parent::` is the parent of the class declaring the method.
        $declaring = $method->identifier->class;
        $parent = $declaring === null ? null : $codebase->getClassLike($declaring)?->directParentClass;
        $inherited = $parent === null ? null : $this->merged($codebase, $parent, $visited);

        return $inherited === null ? null : [...$list->names, ...$inherited];
    }
}
