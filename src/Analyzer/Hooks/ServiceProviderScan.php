<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\ServiceDefinitions;
use amateescu\MagoDrupal\Internal\ServiceProviders;
use Closure;
use Mago\Sdk\Analyzer\CodebaseScanContext;
use Mago\Sdk\Analyzer\CodebaseScanHook;

/**
 * Collects PHP-side service registrations while Mago builds the codebase.
 *
 * Only files under `[source] paths` reach a scan hook; `includes` do not. So
 * a contrib workspace sees its own providers, and core's providers only when
 * core itself is being analyzed. A full analysis runs the scan every time,
 * even when no file matches; an incremental one only when a file it targets
 * changed. So the first batch drops the registrations of the last scan, and
 * Indexes notices every other new analysis by its generation.
 *
 * Only the providers Drupal registers count: core's own and a module's
 * `src/<Module>ServiceProvider.php`, named after the module the way
 * `DrupalKernel::discoverServiceProviders()` builds the class name. The
 * installer adds its providers only while it runs, and a test module's
 * provider only runs in tests, so neither changes the classes a site gets.
 *
 * @internal
 *
 * @phpstan-import-type Definition from \amateescu\MagoDrupal\Internal\ServiceYaml
 */
final class ServiceProviderScan implements CodebaseScanHook
{
    /**
     * @var array<non-empty-string, Definition>
     */
    private array $definitions = [];

    /**
     * @param Closure(): void $reset Runs on the first batch of a scan.
     * @param Closure(array<non-empty-string, Definition>): void $publish
     *   Receives the complete set once the last batch is in.
     */
    public function __construct(
        private readonly Closure $reset,
        private readonly Closure $publish,
    ) {}

    public function getTargets(): array
    {
        return ['**/*ServiceProvider.php'];
    }

    public function scan(CodebaseScanContext $context): void
    {
        if ($context->firstBatch) {
            $this->definitions = [];
            ($this->reset)();
        }

        foreach ($context->files as $file) {
            if (!ServiceProviders::discovered($file->path)) {
                continue;
            }

            $this->definitions = ServiceDefinitions::merge($this->definitions, ServiceProviders::definitions($file));
        }

        if ($context->lastBatch) {
            ($this->publish)($this->definitions);
        }
    }
}
