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

    public function textGate(): ?string
    {
        return null;
    }

    public function check(ClassFacts $class, Reporter $reporter): void
    {
        $hooks = ($this->hooks)($class->codebase);
        foreach (HookMethods::of($class) as [$hook, $method, $location]) {
            $documented = self::reported($hooks, $this->target, $hook);
            if ($documented === null) {
                continue;
            }

            $reporter->warning(self::CODE, self::issue(HookMethods::label($method), $documented, $location));
        }
    }

    /**
     * The documentation function an implementation of the hook is reported
     * against, such as `hook_search_api_query_TAG_alter`. It is `@deprecated`,
     * and the target keeps the deprecation. Null when the implementation is
     * not reported.
     */
    public static function reported(HookFunctions $hooks, DeprecationTarget $target, string $hook): ?string
    {
        $deprecation = $hooks->deprecation("hook_{$hook}");

        return $deprecation !== null && $target->keeps($deprecation[1]) ? $deprecation[0] : null;
    }

    /**
     * The issue for a function or method that implements a deprecated hook.
     */
    public static function issue(string $name, string $documented, Span|SourceLocation $where): Issue
    {
        return Reporter::issue(
            "{$name}() implements {$documented}, which is deprecated.",
            $where,
            "See the @deprecated note on {$documented}() in the module's api.php for the replacement.",
        );
    }
}
