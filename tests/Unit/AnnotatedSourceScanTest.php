<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\Hooks\AnnotatedSourceScan;
use PHPUnit\Framework\TestCase;

use function getcwd;
use function realpath;

final class AnnotatedSourceScanTest extends TestCase
{
    /**
     * Mago names files relative to the workspace, which is where the worker
     * runs, and reads a target as a glob.
     */
    public function testTargetsTheFilesUnderTheWorkerDirectoryAsLiteralGlobs(): void
    {
        $base = (string) realpath((string) getcwd());

        self::assertSame(
            ['src/Plugin/Block/Plain.php', 'src/Plugin/Block/Odd[[]1[]][*].php'],
            AnnotatedSourceScan::targets([
                $base . '/src/Plugin/Block/Plain.php',
                $base . '/src/Plugin/Block/Odd[1]*.php',
                '/elsewhere/src/Plugin/Block/Outside.php',
            ]),
        );
    }
}
