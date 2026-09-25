<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\DeprecatedHookCheck;
use amateescu\MagoDrupal\Analyzer\Checks\EntityOperationCacheabilityCheck;
use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Internal\Attributes;
use amateescu\MagoDrupal\Internal\DeclaredClass;
use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\HookFunctions;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\NodeKind;

use function str_starts_with;
use function strlen;
use function strrchr;
use function substr;

/**
 * Checks procedural hook implementations in `.module`, `.install`, `.inc`
 * and the other procedural files.
 *
 * A function named `<module>_<hook>` implements `hook_<hook>`. Ports the
 * procedural halves of phpstan-drupal's DeprecatedHookImplementation and
 * ProceduralHookEntityOperationCacheabilityRule. The function node's span
 * and the file's resolved names name the function; its metadata gives the
 * signature.
 *
 * @internal
 */
final class ProceduralHookHook implements NodeAnalysisHook
{
    /**
     * Attributes that keep a procedural implementation next to an OOP one on
     * purpose, for older core. `LegacyRequirementsHook` also silences the
     * deprecation on 11.3 and later.
     */
    private const LEGACY = [
        'Drupal\Core\Hook\Attribute\LegacyHook',
        'Drupal\Core\Hook\Attribute\LegacyRequirementsHook',
    ];

    /**
     * @param Closure(Codebase): HookFunctions $hooks
     */
    public function __construct(
        private readonly Closure $hooks,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::Function];
    }

    public function getRequirements(): array
    {
        return [];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = DrupalFile::fromPath($context->analysis->file);
        if (!$file->isProcedural()) {
            return;
        }

        $module = $file->name;
        $candidates = DeclaredClass::candidates($context->source, $context->node);
        if ($candidates === [] || $module === '') {
            return;
        }

        // A bare attribute on the function is a candidate too, so the
        // function is the one declared at the node.
        $function = null;
        foreach ($context->codebase->getMultipleFunctions($candidates) as $candidate) {
            if ($candidate === null || $candidate->location->span->start !== $context->node->span->start) {
                continue;
            }

            $function = $candidate;
            break;
        }

        if ($function === null || self::legacy($function->attributes)) {
            return;
        }

        $tail = strrchr($function->originalName, needle: '\\');
        $short = $tail === false ? $function->originalName : substr($tail, offset: 1);
        if (!str_starts_with($short, $module . '_')) {
            return;
        }

        $hook = substr($short, strlen($module) + 1);
        $hooks = ($this->hooks)($context->codebase);
        $reporter = new Reporter($context);
        // The name, rather than the whole function, is what gets marked.
        $where = $function->nameLocation ?? $function->location;
        if ($hooks->deprecation("hook_{$hook}") === true) {
            $reporter->warning(DeprecatedHookCheck::CODE, DeprecatedHookCheck::issue($short, $hook, $where));
        }

        $position = EntityOperationCacheabilityCheck::missing($hooks, $hook, $function->parameters);
        if ($position !== null) {
            $reporter->error(EntityOperationCacheabilityCheck::CODE, EntityOperationCacheabilityCheck::issue(
                $short,
                $hook,
                $position,
                $where,
            ));
        }
    }

    /**
     * Whether one of the attributes marks a kept legacy implementation.
     *
     * @param list<AttributeMetadata> $attributes
     */
    private static function legacy(array $attributes): bool
    {
        foreach (self::LEGACY as $class) {
            if (Attributes::named($attributes, $class) !== []) {
                return true;
            }
        }

        return false;
    }
}
