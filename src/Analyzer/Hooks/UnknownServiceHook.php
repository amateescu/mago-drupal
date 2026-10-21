<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Analyzer\Providers\Containers;
use amateescu\MagoDrupal\Internal\Drupal\CoreFiles;
use amateescu\MagoDrupal\Internal\Drupal\TestFiles;
use amateescu\MagoDrupal\Internal\Service\ServiceIndex;
use Closure;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;

use function array_key_exists;
use function strpos;
use function substr;

/**
 * Reports container lookups of a service that isn't defined in a services file
 * or provider, or of a private one: the compiled container leaves both out.
 *
 * Only a plain string id is checked. A `Foo::class` id may be a hook class or
 * another autowired service the container registers under its class name
 * without a YAML line, and a lookup that asks for null on a missing service is
 * a probe, not a mistake. An id whose first segment doesn't name a module in
 * the codebase or start a known id belongs to a module the site may not have,
 * as `persistent_login.token_manager` behind a `hasService()` check, so it is
 * left alone, as `config-unknown-name` leaves such a config name. Core looks up
 * only its own services, so in core's own files every id is checked.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
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
     * services file is outside the root and an unknown id doesn't prove
     * anything.
     */
    public const SENTINEL = 'entity_type.manager';

    /**
     * @param Closure(Codebase): ServiceIndex $services Returns the current index.
     * @param Closure(Codebase): array<string, string> $modules Module machine
     *   name to directory.
     */
    public function __construct(
        private readonly Closure $services,
        private readonly Closure $modules,
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
        if ($type === null) {
            return;
        }

        $id = $type->getLiteralString();
        if ($id === null || $id === '' || $type->getLiteralClassString() !== null) {
            return;
        }

        $behavior = ($context->argumentTypes[1] ?? null)?->getLiteralInt();
        if ($behavior !== null && $behavior !== Containers::EXCEPTION_ON_INVALID_REFERENCE) {
            return;
        }

        $services = ($this->services)($context->codebase);
        $service = $services->get($id);
        if (
            !$services->has(self::SENTINEL)
            || $service !== null && $service->public
            || $service === null
            && !CoreFiles::contains($context->analysis->file)
            && $this->optional($context->codebase, $services, $id)
        ) {
            return;
        }

        $span = $context->node->span;
        Reporter::report(
            $context,
            Level::Warning,
            self::CODE,
            $service === null
                ? Issue::new("The service \"{$id}\" isn't defined.", $span, 'unknown service id')->withHelp(
                    'Check the id against the services files, or make sure the module defining it is under the Drupal root.',
                )
                : Issue::new(
                    "The service \"{$id}\" is private, so the container cannot hand it back.",
                    $span,
                    'private service id',
                )->withHelp('Inject it into another service, or look it up through a public alias.'),
        );
    }

    /**
     * Whether an unknown id looks like the service of a module the codebase
     * does not have. An id without a dot is checked, and so is a typo of a
     * known id, whose first segment other ids share.
     */
    private function optional(Codebase $codebase, ServiceIndex $services, string $id): bool
    {
        $dot = strpos($id, needle: '.');
        if ($dot === false || $dot === 0) {
            return false;
        }

        $segment = substr($id, offset: 0, length: $dot);

        return !array_key_exists($segment, ($this->modules)($codebase)) && !$services->hasNamespace($segment);
    }
}
