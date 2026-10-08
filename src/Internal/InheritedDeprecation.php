<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\Metadata\ClassLikeMetadata;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;
use Mago\Sdk\Analyzer\Metadata\MetadataFlags;
use Mago\Sdk\Span;

use function str_contains;

/**
 * Whether the method around a span overrides a deprecated method.
 *
 * PHPStan counts such a method as deprecated itself, unless its docblock says
 * `@not-deprecated`, and its deprecation rules report nothing inside it. A
 * decorator forwarding the deprecated method it implements is the usual case.
 * Mago analyzes a trait once, on its own, so a trait method counts when every
 * class that gets it from the trait inherits a deprecated declaration of it.
 * Finding those classes is a search by method name across the codebase, so
 * only a trait pays for one. A trait among them has no ancestors, so it fails
 * the check and keeps the issue.
 *
 * @internal
 */
final class InheritedDeprecation
{
    private const NOT_DEPRECATED = '@not-deprecated';

    private function __construct() {}

    public static function covers(Codebase $codebase, string $file, NamedFunctions $functions, Span $span): bool
    {
        $function = $functions->at($span->start);
        if ($function === null || $function[2] === null || str_contains($function[1], self::NOT_DEPRECATED)) {
            return false;
        }

        // The tokens name the class-like; its metadata confirms that it is
        // the one this file declares.
        [$name, , $owner] = $function;
        $metadata = $codebase->getClassLike($owner);
        if ($metadata === null || $metadata->location->file !== $file) {
            return false;
        }

        $classes = $metadata->kind === ClassLikeKind::Trait ? TraitUsers::of($codebase, $owner, $name) : [$metadata];
        if ($classes === []) {
            return false;
        }

        foreach ($classes as $class) {
            if ($class === null || !self::overridesDeprecated($codebase, $class, $name)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether an ancestor of the class declares the method deprecated.
     */
    private static function overridesDeprecated(Codebase $codebase, ClassLikeMetadata $class, string $name): bool
    {
        $methods = [];
        foreach ([...$class->parentClasses, ...$class->parentInterfaces] as $ancestor) {
            $methods[] = new MemberIdentifier($ancestor, $name);
        }

        foreach ($methods === [] ? [] : $codebase->getMultipleDeclaringMethods($methods) as $method) {
            if ($method?->flags->contains(MetadataFlags::DEPRECATED) === true) {
                return true;
            }
        }

        return false;
    }
}
