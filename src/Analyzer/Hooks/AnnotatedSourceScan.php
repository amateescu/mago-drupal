<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\AnnotatedDeclarations;
use Closure;
use Mago\Sdk\Analyzer\CodebaseScanContext;
use Mago\Sdk\Analyzer\CodebaseScanHook;

use function getcwd;
use function preg_match;
use function preg_replace;
use function realpath;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Collects the text Mago analyzes for the analyzed files that have a legacy
 * annotation on disk.
 *
 * Plugins and entity types declared with a docblock annotation are read off
 * disk, which in an editor session can differ from the text Mago analyzes:
 * a renamed plugin id would still resolve under its old name and not under
 * the new one. Mago hands a scan hook the analyzed text of every file it
 * targets, so the annotation index reads those files from here instead. Only
 * files under `[source] paths` reach the hook; `includes` are read off disk.
 * Mago runs the scan again with all of them whenever one of them changes.
 *
 * Mago sends every targeted file's whole syntax tree to every worker, so the
 * targets are the files annotated on disk when the worker starts, not every
 * plugin file: on core that is a tenth of them. An annotation added to a
 * file that had none is read once the file is saved.
 *
 * @internal
 */
final class AnnotatedSourceScan implements CodebaseScanHook
{
    /**
     * @var array<string, string|null>
     */
    private array $sources = [];

    /**
     * @param Closure(array<string, string|null>): void $publish Receives the
     *   scanned text by real path, null for a file without an annotation,
     *   once the last batch is in.
     */
    /**
     * @param non-empty-list<non-empty-string> $targets See targets().
     */
    public function __construct(
        private readonly array $targets,
        private readonly Closure $publish,
    ) {}

    /**
     * The files as source-file globs relative to the worker's directory,
     * where Mago's file names start. A file outside it is left out.
     *
     * @param list<string> $paths Real paths.
     * @return list<non-empty-string>
     */
    public static function targets(array $paths): array
    {
        $cwd = getcwd();
        $base = $cwd === false ? false : realpath($cwd);
        if ($base === false) {
            return [];
        }

        $targets = [];
        foreach ($paths as $path) {
            if (!str_starts_with($path, $base . '/')) {
                continue;
            }

            // A bracket class matches the character itself on every platform.
            $relative = substr($path, offset: strlen($base) + 1);
            $target = (string) preg_replace('/[*?\[\]{}]/', replacement: '[$0]', subject: $relative);
            if ($target !== '') {
                $targets[] = $target;
            }
        }

        return $targets;
    }

    public function getTargets(): array
    {
        return $this->targets;
    }

    public function scan(CodebaseScanContext $context): void
    {
        if ($context->firstBatch) {
            $this->sources = [];
        }

        foreach ($context->files as $file) {
            $annotated = preg_match(AnnotatedDeclarations::GATE, $file->contents) === 1;
            $this->sources[self::realPath($file->path)] = $annotated ? $file->contents : null;
        }

        if ($context->lastBatch) {
            ($this->publish)($this->sources);
        }
    }

    /**
     * The path the disk index uses.
     */
    private static function realPath(string $path): string
    {
        $real = realpath($path);

        return $real === false ? $path : $real;
    }
}
