<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\AnnotatedDeclarations;
use Closure;
use Mago\Sdk\Analyzer\CodebaseScanContext;
use Mago\Sdk\Analyzer\CodebaseScanHook;

use function preg_match;

/**
 * Collects legacy annotation declarations from entity, plugin and render
 * element classes.
 *
 * Only files under `[source] paths` reach a scan hook, which is where contrib
 * annotations live; core is on attributes. The host sends every batch to
 * every worker, so each worker ends up with the complete set.
 *
 * @internal
 */
final class AnnotationScan implements CodebaseScanHook
{
    /**
     * A docblock line starting an annotation; files without one are not
     * parsed at all.
     */
    private const GATE = '/^\s*\*\s*@[A-Z]/m';

    /**
     * @var list<AnnotatedDeclarations>
     */
    private array $found = [];

    /**
     * @param Closure(AnnotatedDeclarations): void $publish Receives the
     *   complete set once the last batch is in.
     */
    public function __construct(
        private readonly Closure $publish,
    ) {}

    public function getTargets(): array
    {
        return ['**/src/Entity/*.php', '**/src/Plugin/**/*.php', '**/src/Element/*.php'];
    }

    public function scan(CodebaseScanContext $context): void
    {
        if ($context->firstBatch) {
            $this->found = [];
        }

        foreach ($context->files as $file) {
            if (preg_match(self::GATE, $file->contents) !== 1) {
                continue;
            }

            $declarations = AnnotatedDeclarations::read($file);
            if (!$declarations->isEmpty()) {
                $this->found[] = $declarations;
            }
        }

        if (!$context->lastBatch) {
            return;
        }

        $all = AnnotatedDeclarations::mergeAll($this->found);
        $this->found = [];
        ($this->publish)($all);
    }
}
