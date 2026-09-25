<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Providers\Containers;
use amateescu\MagoDrupal\Analyzer\Providers\ServiceIds;
use amateescu\MagoDrupal\Internal\ServiceIndex;
use amateescu\MagoDrupal\Internal\TestFiles;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

/**
 * Reports container lookups of services whose definition says `deprecated:`.
 *
 * Mago's own deprecation checks read `@deprecated` on PHP symbols. A service
 * deprecation lives in YAML, so only the index can see it.
 *
 * @internal
 */
final class DeprecatedServiceHook implements MethodCallAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/deprecated-service`.
     */
    public const CODE = 'deprecated-service';

    /**
     * @param Closure(Codebase): ServiceIndex $services Returns the current index.
     */
    public function __construct(
        private readonly Closure $services,
    ) {}

    public function getTargets(): array
    {
        // `has()` is how code probes for a service without triggering its
        // deprecation, so only the lookups that instantiate are targeted.
        return [
            MethodTarget::exact(Containers::INTERFACE, 'get'),
            MethodTarget::exact('Drupal', 'service'),
            MethodTarget::exact('Drupal', 'classResolver'),
            MethodTarget::exact(Containers::CLASS_RESOLVER, 'getInstanceFromDefinition'),
        ];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::ArgumentTypes];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if (TestFiles::isTestOrHookDocumentation($context->analysis->file)) {
            return;
        }

        // Argument types arrive in source order. Every target takes the id
        // first and an int or array second, so a call naming them out of
        // order fails the string test in fromType().
        $id = ServiceIds::fromType($context->argumentTypes[0] ?? null);
        if ($id === null) {
            return;
        }

        $deprecation = ($this->services)($context->codebase)->get($id)?->deprecation;
        if ($deprecation === null) {
            return;
        }

        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new("The \"{$id}\" service is deprecated.", $context->node->span, 'deprecated service')->withNote(
                $deprecation,
            )->withHelp('Use the replacement the deprecation message names.'),
        );
    }
}
