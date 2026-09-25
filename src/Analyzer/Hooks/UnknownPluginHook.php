<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Providers\PluginManagerProvider;
use amateescu\MagoDrupal\Internal\PluginIndex;
use amateescu\MagoDrupal\Internal\PluginManagers;
use amateescu\MagoDrupal\Internal\TestFiles;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function str_contains;

/**
 * Reports `createInstance('id')` on a core manager when no scanned plugin
 * declares that id.
 *
 * @internal
 */
final class UnknownPluginHook implements MethodCallAnalysisHook
{
    /**
     * The host prefixes this with the plugin id: `drupal/unknown-plugin`.
     */
    public const CODE = 'unknown-plugin';

    /**
     * @param Closure(Codebase): PluginIndex $index
     */
    public function __construct(
        private readonly Closure $index,
    ) {}

    public function getTargets(): array
    {
        return [MethodTarget::exact(PluginManagerProvider::FACTORY, 'createInstance')];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::ReceiverType, FileAnalysisRequirement::ArgumentTypes];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if (TestFiles::isTestOrHookDocumentation($context->analysis->file)) {
            return;
        }

        $attribute = PluginManagerProvider::attributeOf($context->codebase, $context->receiverType);
        // Argument types arrive in source order. The other parameter is an
        // array, so a call naming them out of order fails the string test.
        $id = ($context->argumentTypes[0] ?? null)?->getLiteralString();
        // A `base:derivative` id comes from a deriver and cannot be checked.
        if ($attribute === null || $id === null || $id === '' || str_contains($id, ':')) {
            return;
        }

        // The sentinel is a core plugin that is always present once core's
        // plugins were scanned. Without it, core is outside the analyzed code
        // and an unknown id proves nothing.
        $sentinel = PluginManagers::SENTINELS[$attribute] ?? null;
        $index = ($this->index)($context->codebase);
        if (
            $sentinel === null
            || $index->classOf($attribute, $sentinel) === null
            || $index->declares($attribute, $id)
        ) {
            return;
        }

        $fallback = PluginManagers::FALLBACKS[$attribute] ?? null;
        $help = $fallback === null
            ? 'Check the id against the plugin classes, or make sure the module providing it is part of the analyzed code.'
            : "The manager falls back to the \"{$fallback}\" plugin at runtime, so this does not throw but does not do what the code expects either.";
        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                "No plugin with id \"{$id}\" carries #[{$attribute}].",
                $context->node->span,
                'unknown plugin id',
            )->withHelp($help),
        );
    }
}
