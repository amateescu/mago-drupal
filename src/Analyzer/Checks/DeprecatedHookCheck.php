<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Checks;

use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\DeprecationTarget;
use amateescu\MagoDrupal\Internal\HookFunctions;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Span;

/**
 * Reports `#[Hook]` methods implementing a hook whose `hook_*()` documentation
 * function is `@deprecated`.
 *
 * Ports the OOP half of phpstan-drupal's DeprecatedHookImplementation; the
 * procedural half lives in ProceduralHookHook.
 *
 * @internal
 */
final class DeprecatedHookCheck implements MetadataCheck
{
    public const CODE = 'deprecated-hook';

    /**
     * @param Closure(Codebase): HookFunctions $hooks
     */
    public function __construct(
        private readonly Closure $hooks,
        private readonly DeprecationTarget $target,
    ) {}

    public function mentionsAny(): array
    {
        return [];
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $hooks = ($this->hooks)($class->codebase);
        foreach (HookMethods::of($class) as [$hook, $method]) {
            $location = $method->nameLocation ?? $method->location;
            if ($location === null || !self::reports($hooks, $this->target, $hook)) {
                continue;
            }

            $reporter->warning(self::CODE, self::issue(HookMethods::label($method), $hook, $location));
        }
    }

    /**
     * Whether an implementation of the hook is reported: its documentation
     * function is `@deprecated`, and the target keeps the deprecation.
     */
    public static function reports(HookFunctions $hooks, DeprecationTarget $target, string $hook): bool
    {
        $deprecation = $hooks->deprecation("hook_{$hook}");

        return $deprecation !== null && $target->keeps($deprecation);
    }

    /**
     * The issue for a function or method that implements a deprecated hook.
     */
    public static function issue(string $name, string $hook, Span|SourceLocation $where): Issue
    {
        return Reporter::issue(
            "{$name}() implements hook_{$hook}, which is deprecated.",
            $where,
            "See the @deprecated note on hook_{$hook}() in the module's api.php for the replacement.",
        );
    }
}
