<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\Checks\ListBuilderCacheabilityCheck;
use PHPUnit\Framework\TestCase;

final class ListBuilderCacheabilityCheckTest extends TestCase
{
    public function testComparesMajorAndMinorOnly(): void
    {
        self::assertFalse(ListBuilderCacheabilityCheck::commentedOut('11.2.9'));
        self::assertTrue(ListBuilderCacheabilityCheck::commentedOut('11.3.0'));
        // version_compare() puts a development release below its release.
        self::assertTrue(ListBuilderCacheabilityCheck::commentedOut('11.3-dev'));
        self::assertTrue(ListBuilderCacheabilityCheck::commentedOut('11.4.6'));
        self::assertFalse(ListBuilderCacheabilityCheck::commentedOut('12.0-dev'));
        self::assertFalse(ListBuilderCacheabilityCheck::commentedOut(null));
    }
}
