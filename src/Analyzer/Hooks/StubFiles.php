<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use Mago\Sdk\Analyzer\InitializationContext;
use Mago\Sdk\Analyzer\InitializationHook;

use function basename;
use function dirname;
use function file_get_contents;
use function glob;
use function sort;

/**
 * Loads the shipped stub files into the analyzer's symbol table.
 *
 * The stubs sharpen core signatures Drupal documents loosely. Mago reads them
 * for symbols only: they are never linted, formatted or reported on, and a
 * member the stub does not name keeps the declaration core ships.
 *
 * @internal
 */
final class StubFiles implements InitializationHook
{
    private readonly string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?? dirname(__DIR__, levels: 3) . '/resources/stubs';
    }

    public function initialize(InitializationContext $context): void
    {
        foreach ($this->files() as $file) {
            $bytes = file_get_contents($file);
            if ($bytes === false) {
                continue;
            }

            $context->addStub(basename($file), $bytes);
        }
    }

    /**
     * The stub files, in a stable order so every worker sees the same set.
     *
     * @return list<string>
     */
    public function files(): array
    {
        $files = glob($this->directory . '/*.stub');
        if ($files === false) {
            return [];
        }

        sort($files);

        return $files;
    }
}
