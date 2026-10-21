<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

use function array_intersect;
use function array_unique;
use function array_values;
use function dirname;
use function explode;
use function file_get_contents;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function sort;
use function str_replace;
use function strlen;
use function substr;
use function trim;

/**
 * Keeps the linter and the analyzer apart, and the shared code under
 * `Internal` free of both.
 *
 * A file's layer is the namespace segment after `amateescu\MagoDrupal`,
 * whatever directory the file is in. The test looks for the namespaces of
 * the other layers in `use` statements, fully qualified names, docblocks and
 * strings. Files directly in `amateescu\MagoDrupal`, such as
 * `DrupalExtension`, wire both halves together and aren't checked.
 */
final class LayersTest extends TestCase
{
    /**
     * The layers that each layer must not name.
     */
    private const FORBIDDEN = [
        'Linter' => ['Analyzer'],
        'Analyzer' => ['Linter'],
        'Internal' => ['Linter', 'Analyzer'],
    ];

    public function testLayersDontNameEachOther(): void
    {
        $src = dirname(__DIR__, levels: 2) . '/src/';
        $problems = [];
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($src, flags: RecursiveDirectoryIterator::SKIP_DOTS),
        );
        foreach ($files as $file) {
            if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());
            $forbidden = self::FORBIDDEN[self::layer($contents)] ?? [];
            foreach (array_intersect(self::namedLayers($contents), $forbidden) as $named) {
                $problems[] = substr($file->getPathname(), offset: strlen($src)) . " names {$named}";
            }
        }

        sort($problems);

        self::assertSame([], $problems);
    }

    public function testFindsTheNamedLayers(): void
    {
        $contents = <<<'PHP'
            <?php

            namespace amateescu\MagoDrupal\Internal\Sub;

            use amateescu\MagoDrupal\{Internal\Syntax\Calls, Linter\CallRule};

            /**
             * @see \amateescu\MagoDrupal\Analyzer\DrupalPlugin
             */
            final class Example
            {
                private const NAME = 'amateescu\\MagoDrupal\\Analyzer\\Hooks\\TestClassHook';
            }
            PHP;

        self::assertSame('Internal', self::layer($contents));
        self::assertSame(['Analyzer', 'Internal', 'Linter'], self::namedLayers($contents));
    }

    /**
     * Returns the layer of a file, or an empty string when its namespace is
     * outside the layers.
     */
    private static function layer(string $contents): string
    {
        $matches = [];
        if (preg_match('/^namespace\s+amateescu\\\\MagoDrupal\\\\(\w+)/m', $contents, $matches) !== 1) {
            return '';
        }

        return $matches[1];
    }

    /**
     * Returns the layers that a file names outside its namespace statement,
     * sorted.
     *
     * @return list<string>
     */
    private static function namedLayers(string $contents): array
    {
        $contents = (string) preg_replace('/^namespace\s[^;]+;/m', replacement: '', subject: $contents);
        $contents = str_replace(search: '\\\\', replace: '\\', subject: $contents);
        $layers = [];

        $matches = [];
        preg_match_all('/amateescu\\\\MagoDrupal\\\\(\w+)/', $contents, $matches);
        foreach ($matches[1] as $layer) {
            $layers[] = $layer;
        }

        $groups = [];
        preg_match_all('/amateescu\\\\MagoDrupal\\\\\{([^}]*)\}/', $contents, $groups);
        foreach ($groups[1] as $group) {
            foreach (explode(separator: ',', string: $group) as $name) {
                $layers[] = explode(separator: '\\', string: trim($name), limit: 2)[0];
            }
        }

        $layers = array_values(array_unique($layers));
        sort($layers);

        return $layers;
    }
}
