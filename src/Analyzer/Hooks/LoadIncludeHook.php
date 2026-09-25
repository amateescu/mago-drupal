<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\Arguments;
use amateescu\MagoDrupal\Internal\TestFiles;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function is_file;

/**
 * Reports `loadInclude()` calls naming a file that does not exist.
 *
 * Ports phpstan-drupal's LoadIncludes. The module handler looks for
 * `<module dir>/<name>.<type>`, `<name>` defaulting to the module.
 *
 * @internal
 */
final class LoadIncludeHook implements MethodCallAnalysisHook
{
    public const CODE = 'load-include';

    /**
     * @param Closure(Codebase): array<string, string> $modules Module machine name
     *   to directory.
     */
    public function __construct(
        private readonly Closure $modules,
    ) {}

    public function getTargets(): array
    {
        return [MethodTarget::exact('Drupal\Core\Extension\ModuleHandlerInterface', 'loadInclude')];
    }

    public function getRequirements(): array
    {
        return [
            FileAnalysisRequirement::ArgumentTypes,
            FileAnalysisRequirement::TargetSubtree,
            FileAnalysisRequirement::SourceText,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        // Tests mock the module handler and load fixture includes.
        if (TestFiles::isTestOrHookDocumentation($context->analysis->file)) {
            return;
        }

        $module = Arguments::type($context, position: 0, names: 'module')?->getLiteralString();
        $type = Arguments::type($context, position: 1, names: 'type')?->getLiteralString();
        // An absent third argument means the module's own file; one that is
        // present but not a literal cannot be checked at all.
        $third = Arguments::sourceIndex($context, position: 2, names: 'name');
        $name = $third === null ? $module : ($context->argumentTypes[$third] ?? null)?->getLiteralString();
        if ($module === null || $type === null || $name === null) {
            return;
        }

        // The module handler falls back to the module for an empty name.
        $name = $name === '' ? $module : $name;
        $directory = ($this->modules)($context->codebase)[$module] ?? null;
        if ($directory === null) {
            $context->report(
                Level::Warning,
                self::CODE,
                Issue::new(
                    "loadInclude() names the module \"{$module}\", which is not in the analyzed code.",
                    $context->node->span,
                    'unknown module',
                )->withHelp(
                    'Make sure the module is part of the project, or spell its machine name the way its info file does.',
                ),
            );

            return;
        }

        $file = "{$directory}/{$name}.{$type}";
        if (is_file($file)) {
            return;
        }

        $context->report(
            Level::Error,
            self::CODE,
            Issue::new(
                "loadInclude() names {$name}.{$type} in the {$module} module, but that file does not exist.",
                $context->node->span,
                'missing include',
            )->withHelp("The module handler would look for {$file}."),
        );
    }
}
