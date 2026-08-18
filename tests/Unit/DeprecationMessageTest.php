<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DeprecationMessage;
use amateescu\MagoDrupal\Internal\DeprecationStandard;
use PHPUnit\Framework\TestCase;

final class DeprecationMessageTest extends TestCase
{
    private const VALID = 'foo() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar(). See https://www.drupal.org/node/1234567';

    public function testAcceptsTheStrictFormat(): void
    {
        self::assertSame([], DeprecationMessage::problems(self::VALID, DeprecationStandard::Strict));
    }

    public function testAcceptsFreeRemovalWordingWhenRelaxed(): void
    {
        $message = 'foo() is deprecated in drupal:10.1.0 and will be removed before drupal:11.0.0. See https://www.drupal.org/node/1234567';

        self::assertSame([], DeprecationMessage::problems($message, DeprecationStandard::Relaxed));
    }

    public function testRejectsFreeRemovalWordingWhenStrict(): void
    {
        $message = 'foo() is deprecated in drupal:10.1.0 and will be removed before drupal:11.0.0. See https://www.drupal.org/node/1234567';

        $problems = DeprecationMessage::problems($message, DeprecationStandard::Strict);

        self::assertCount(1, $problems);
        self::assertStringContainsString('strict standard format', $problems[0]);
    }

    public function testRejectsAMissingChangeRecord(): void
    {
        $message = 'foo() is deprecated in drupal:10.1.0 and is removed from drupal:11.0.0. Use bar().';

        self::assertNotSame([], DeprecationMessage::problems($message, DeprecationStandard::Strict));
    }

    public function testRejectsVersionsWithoutAProject(): void
    {
        $message = 'foo() is deprecated in 10.1.0 and is removed from 11.0.0. Use bar(). See https://www.drupal.org/node/1234567';

        $problems = DeprecationMessage::problems($message, DeprecationStandard::Strict);

        self::assertCount(2, $problems);
        self::assertStringContainsString('deprecation version', $problems[0]);
        self::assertStringContainsString('removal version', $problems[1]);
    }

    public function testAcceptsContribVersions(): void
    {
        $message = 'foo() is deprecated in paragraphs:8.x-1.12 and is removed from paragraphs:8.x-2.0. Use bar(). See https://www.drupal.org/project/paragraphs/issues/1234567';

        self::assertSame([], DeprecationMessage::problems($message, DeprecationStandard::Strict));
    }

    public function testCallsOutATrailingPeriodOnTheLink(): void
    {
        $problems = DeprecationMessage::problems(self::VALID . '.', DeprecationStandard::Strict);

        self::assertCount(1, $problems);
        self::assertStringContainsString('should not end with a period', $problems[0]);
    }
}
