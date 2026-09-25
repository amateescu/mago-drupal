<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\AnalysisGeneration;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Internal\Analyzer\MetadataCache;
use Mago\Sdk\Internal\HostClient;
use Mago\Sdk\Internal\Io\ResourceWriter;
use Mago\Sdk\Internal\Protocol\FrameCodec;
use Mago\Sdk\Internal\SignalCancellationToken;
use PHPUnit\Framework\TestCase;

use function fopen;

/**
 * The generation is read out of the SDK by reflection, so a test has to run
 * against a real codebase object: a rename upstream has to fail here rather
 * than turn the shared cache off in silence.
 */
final class AnalysisGenerationTest extends TestCase
{
    public function testReadsTheGenerationMagoGaveTheCodebase(): void
    {
        self::assertSame(42, AnalysisGeneration::of(self::codebase(42)));
        self::assertSame(7, AnalysisGeneration::of(self::codebase(7)));
    }

    /**
     * A codebase whose requests carry the given generation.
     */
    public static function codebase(int $generation): Codebase
    {
        $stream = fopen('php://memory', mode: 'w');
        self::assertIsResource($stream);

        return new Codebase(
            new HostClient(new FrameCodec(), new ResourceWriter($stream)),
            1,
            new SignalCancellationToken(),
            new MetadataCache($generation),
        );
    }
}
