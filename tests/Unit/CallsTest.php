<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Calls;
use PHPUnit\Framework\TestCase;

final class CallsTest extends TestCase
{
    public function testNormalizeDropsTheLeadingSeparator(): void
    {
        self::assertSame('format_date', Calls::normalize('\\format_date'));
        self::assertSame('format_date', Calls::normalize('format_date'));
        self::assertSame('t', Calls::normalize('T'));
    }

    /**
     * Rules keying a table by name have to normalize the same way matching
     * does, or a fully qualified call matches and then misses the lookup.
     */
    public function testNormalizeAgreesWithMatches(): void
    {
        foreach (['\\format_date', 'FORMAT_DATE', 'format_date'] as $written) {
            self::assertTrue(Calls::matches($written, 'format_date'));
            self::assertSame('format_date', Calls::normalize($written));
        }
    }

    public function testMatchesRejectsOtherNames(): void
    {
        self::assertFalse(Calls::matches('format_datetime', 'format_date'));
        self::assertFalse(Calls::matches('\\Drupal\\format_date', 'format_date'));
    }

    /**
     * Finders look candidates up in this set once per descendant, so its keys
     * have to be exactly what normalize() produces.
     */
    public function testNormalizeAllBuildsALookupSet(): void
    {
        self::assertSame(['format_date' => true, 't' => true], Calls::normalizeAll(['\\format_date', 'T']));
    }
}
