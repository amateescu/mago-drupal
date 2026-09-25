<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ServiceDefinitions;
use PHPUnit\Framework\TestCase;

use function dirname;

final class ServiceDefinitionsTest extends TestCase
{
    public function testReadsRegisteredIdsOffProviderFiles(): void
    {
        $ids = ServiceDefinitions::idsInFiles([
            dirname(__DIR__) . '/fixtures/services/SampleServiceProvider.php',
            dirname(__DIR__) . '/fixtures/services/MissingServiceProvider.php',
        ]);

        self::assertSame(['sample.plain', 'sample.chained', 'sample.defined', 'sample.alias'], array_keys($ids));
        self::assertSame([], $ids['sample.plain']);
    }

    public function testMergeKeepsAClassOverAnEntryWithout(): void
    {
        $base = ['a' => ['class' => 'A'], 'b' => []];
        $merged = ServiceDefinitions::merge($base, ['a' => [], 'b' => ['class' => 'B'], 'c' => []]);

        self::assertSame(['a' => ['class' => 'A'], 'b' => ['class' => 'B'], 'c' => []], $merged);
    }
}
