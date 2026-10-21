<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\AnalysisGeneration;
use amateescu\MagoDrupal\Internal\AnalysisMemo;
use Mago\Sdk\Analyzer\Codebase;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class AnalysisMemoTest extends TestCase
{
    protected function tearDown(): void
    {
        // A failed read stops AnalysisGeneration from trying again in this
        // process, which the other tests would see.
        (new ReflectionProperty(AnalysisGeneration::class, 'unavailable'))->setValue(null, false);
    }

    public function testKeepsValuesForOneAnalysis(): void
    {
        /** @var AnalysisMemo<string> $memo */
        $memo = new AnalysisMemo();
        $first = AnalysisGenerationTest::codebase(1);
        $next = AnalysisGenerationTest::codebase(2);

        self::assertSame('first', $memo->get($first, 'key', static fn(): string => 'first'));
        self::assertSame('first', $memo->get($first, 'key', static fn(): string => 'again'));
        self::assertSame('next', $memo->get($next, 'key', static fn(): string => 'next'));
    }

    /**
     * Without the generation, one analysis can't be told from the next, so a
     * kept value could belong to an analysis that is over.
     */
    public function testKeepsNothingWhenTheGenerationCannotBeRead(): void
    {
        /** @var AnalysisMemo<string> $memo */
        $memo = new AnalysisMemo();
        $codebase = AnalysisGenerationTest::codebase(1);
        self::assertSame('first', $memo->get($codebase, 'key', static fn(): string => 'first'));

        // A codebase without its metadata cache makes the reflection fail,
        // the way it fails once the SDK renames the properties.
        $broken = (new ReflectionClass(Codebase::class))->newInstanceWithoutConstructor();
        self::assertNull(AnalysisGeneration::of($broken));
        self::assertNull(AnalysisGeneration::of($codebase));

        self::assertSame('second', $memo->get($codebase, 'key', static fn(): string => 'second'));
        self::assertSame('third', $memo->get($codebase, 'key', static fn(): string => 'third'));
    }
}
