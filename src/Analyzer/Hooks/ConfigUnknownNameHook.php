<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Providers\Configs;
use amateescu\MagoDrupal\Internal\ConfigSchema;
use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\TestFiles;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function array_key_exists;
use function explode;

/**
 * Reports a config object asked for by a name no schema describes.
 *
 * A misspelt name hands back an empty config object without complaint, and
 * config without a schema fails Drupal's strict schema checks in tests. Only
 * a literal name is checked, and only when the module the name starts with is
 * in the codebase: a module reading an optional module's config cannot expect
 * its schema. Test code and hook documentation make up names, and update code
 * reads config that today's schema may no longer cover.
 *
 * @internal
 */
final class ConfigUnknownNameHook implements MethodCallAnalysisHook
{
    /**
     * Reported as `drupal/config-unknown-name` once the host adds the plugin
     * prefix.
     */
    public const CODE = 'config-unknown-name';

    /**
     * A config name core's own schema describes. Without it, core's schema
     * files are outside the root and a missing schema proves nothing.
     */
    public const SENTINEL = 'core.extension';

    /**
     * The provider of `core.*` config, which is no module.
     */
    private const CORE = 'core';

    /**
     * @param Closure(Codebase): ConfigSchema $schema
     * @param Closure(Codebase): array<string, string> $modules Module machine
     *   name to directory.
     */
    public function __construct(
        private readonly Closure $schema,
        private readonly Closure $modules,
    ) {}

    public function getTargets(): array
    {
        return Configs::loaders();
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::ArgumentTypes];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = $context->analysis->file;
        if (TestFiles::isTestOrHookDocumentation($file) || DrupalFile::fromPath($file)->isUpdate()) {
            return;
        }

        $name = ($context->argumentTypes[0] ?? null)?->getLiteralString();
        if ($name === null || $name === '') {
            return;
        }

        $codebase = $context->codebase;
        $provider = explode('.', $name, limit: 2)[0];
        if ($provider !== self::CORE && !array_key_exists($provider, ($this->modules)($codebase))) {
            return;
        }

        $schema = ($this->schema)($codebase);
        if ($schema->definitionName(self::SENTINEL) === null || $schema->definitionName($name) !== null) {
            return;
        }

        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new("No config schema describes \"{$name}\".", $context->node->span, 'no schema')->withHelp(
                "Check the name for a typo, or describe the config in the module's config/schema directory.",
            ),
        );
    }
}
