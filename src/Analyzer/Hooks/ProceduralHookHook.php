<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\DeprecatedHookCheck;
use amateescu\MagoDrupal\Analyzer\Checks\EntityOperationCacheabilityCheck;
use amateescu\MagoDrupal\Analyzer\Checks\FormAlterSignatureCheck;
use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Internal\Attributes;
use amateescu\MagoDrupal\Internal\DeprecationTarget;
use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\HookFunctions;
use amateescu\MagoDrupal\Internal\ProceduralFunctions;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\AttributeMetadata;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Syntax\NodeKind;

use function str_starts_with;
use function strlen;
use function substr;

/**
 * Checks procedural hook implementations in `.module`, `.install`, `.inc`
 * and the other procedural files.
 *
 * A function named `<module>_<hook>` implements `hook_<hook>`. Ports the
 * procedural halves of phpstan-drupal's DeprecatedHookImplementation and
 * ProceduralHookEntityOperationCacheabilityRule, and checks form alter
 * signatures as FormAlterSignatureCheck does for methods. The function
 * node's span and the file's resolved names name the function; its metadata
 * gives the signature.
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
        private readonly DeprecationTarget $target,
    ) {}

    public function getTargets(): array
    {
        return [NodeKind::Function];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = DrupalFile::fromPath($context->analysis->file);
        $module = $file->name;
        if (!$file->isProcedural() || $module === '') {
            return;
        }

        // The name is read off the text first, so only a function named like
        // a hook of this module costs a codebase request.
        $contents = $context->source->contents;
        $short = ProceduralFunctions::nameAt($contents, $context->node->span->start);
        if (
            $short === null
            || !str_starts_with($short, $module . '_')
            || ProceduralFunctions::afterScanStop($contents, $context->node->span->end)
        ) {
            return;
        }

        $function = ProceduralFunctions::declared($context, $short);
        if ($function === null || self::legacy($function->attributes)) {
            return;
        }

        $hook = substr($short, strlen($module) + 1);
        $hooks = ($this->hooks)($context->codebase);
        $reporter = new Reporter($context);
        // The name, rather than the whole function, is what gets marked.
        $where = $function->nameLocation ?? $function->location;
        $documented = DeprecatedHookCheck::reported($hooks, $this->target, $hook);
        if ($documented !== null) {
            $reporter->warning(DeprecatedHookCheck::CODE, DeprecatedHookCheck::issue($short, $documented, $where));
        }

        $problems = FormAlterSignatureCheck::isFormAlter($hook)
            ? FormAlterSignatureCheck::problems($function->parameters)
            : [];
        if ($problems !== []) {
            $reporter->error(FormAlterSignatureCheck::CODE, FormAlterSignatureCheck::issue(
                $short,
                $hook,
                $problems,
                $where,
            ));
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
