<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Providers\Containers;
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
 * Reports container lookups of an id no services file or provider defines,
 * or of a private one: the compiled container leaves both out.
 *
 * Only a plain string id is checked. A `Foo::class` id may be a hook class or
 * another autowired service the container registers under its class name
 * without a YAML line, and a lookup that asks for null on a missing service
 * is a probe, not a mistake.
 *
 * @internal
 */
final class UnknownServiceHook implements MethodCallAnalysisHook
{
    /**
     * Mago prefixes hook codes with the plugin identifier, so this is reported
     * as `drupal/unknown-service`.
     */
    public const CODE = 'unknown-service';

    /**
     * A core service every Drupal site has. Without it in the index, core's
     * services file is outside the root and an unknown id proves nothing.
     */
    public const SENTINEL = 'entity_type.manager';

    /**
     * @param Closure(Codebase): ServiceIndex $services Returns the current index.
     */
    public function __construct(
        private readonly Closure $services,
    ) {}

    public function getTargets(): array
    {
        return [
            MethodTarget::exact(Containers::INTERFACE, 'get'),
            MethodTarget::exact('Drupal', 'service'),
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

        // Argument types arrive in source order. `get()` takes the id and
        // an int behaviour, so a call naming them out of order puts the int
        // first and the literal string test below drops it.
        $type = $context->argumentTypes[0] ?? null;
        $id = $type?->getLiteralString();
        if ($id === null || $id === '' || $type?->getLiteralClassString() !== null) {
            return;
        }

        $behavior = ($context->argumentTypes[1] ?? null)?->getLiteralInt();
        if ($behavior !== null && $behavior !== Containers::EXCEPTION_ON_INVALID_REFERENCE) {
            return;
        }

        $services = ($this->services)($context->codebase);
        $service = $services->get($id);
        if (!$services->has(self::SENTINEL) || $service !== null && $service->public) {
            return;
        }

        $span = $context->node->span;
        $context->report(
            Level::Warning,
            self::CODE,
            $service === null
                ? Issue::new("No service with id \"{$id}\" is defined.", $span, 'unknown service id')->withHelp(
                    'Check the id against the services files, or make sure the module defining it is under the Drupal root.',
                )
                : Issue::new(
                    "The service \"{$id}\" is private, so the container cannot hand it back.",
                    $span,
                    'private service id',
                )->withHelp('Inject it into another service, or look it up through a public alias.'),
        );
    }
}
