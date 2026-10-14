<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Providers\Configs;
use amateescu\MagoDrupal\Internal\ConfigSchema;
use amateescu\MagoDrupal\Internal\DrupalFile;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

/**
 * Reports `$config->get('key')` for a key the schema does not declare.
 *
 * Update code is left alone: it reads the keys an older version of the module
 * wrote, which today's schema no longer lists.
 *
 * @internal
 */
final class ConfigUnknownKeyHook implements MethodCallAnalysisHook
{
    /**
     * Reported as `drupal/config-unknown-key` once the host adds the plugin
     * prefix.
     */
    public const CODE = 'config-unknown-key';

    /**
     * @param Closure(Codebase): ConfigSchema $schema
     */
    public function __construct(
        private readonly Closure $schema,
    ) {}

    public function getTargets(): array
    {
        return [MethodTarget::exact(Configs::BASE, 'get')];
    }

    public function getRequirements(): array
    {
        return [FileAnalysisRequirement::ReceiverType, FileAnalysisRequirement::ArgumentTypes];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        // Update code reads the config as an older version of the module
        // wrote it, and moving those keys is what it is there for, so
        // today's schema says nothing about them. Navigation's two update
        // hooks alone read nine keys they then remove.
        if (DrupalFile::fromPath($context->analysis->file)->isUpdate()) {
            return;
        }

        $name = Configs::nameOf($context->receiverType);
        // `get()` takes the key alone, so naming it changes nothing.
        $key = ($context->argumentTypes[0] ?? null)?->getLiteralString();
        if ($name === null || $key === null || $key === '') {
            return;
        }

        // Only a fully validatable schema lists every key, so only there is a
        // missing key a real mistake.
        $schema = ($this->schema)($context->codebase);
        if (!$schema->isFullyValidatable($name) || $schema->keyExists($name, $key)) {
            return;
        }

        $context->report(
            Level::Error,
            self::CODE,
            Issue::new(
                "Config key \"{$key}\" does not exist in the schema for \"{$name}\".",
                $context->node->span,
                'unknown key',
            )->withHelp(
                'Check the key against the config schema; the schema is fully validatable, so every key is listed.',
            ),
        );
    }
}
