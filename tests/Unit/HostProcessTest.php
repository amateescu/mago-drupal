<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\HostProcess;
use PHPUnit\Framework\TestCase;

use function getmypid;
use function is_dir;

final class HostProcessTest extends TestCase
{
    public function testNamesTheSameProcessTheSameWay(): void
    {
        self::assertSame(HostProcess::identity(), HostProcess::identity());
        self::assertStringStartsWith((string) getmypid() . '-', HostProcess::identify((int) getmypid()));
    }

    public function testTellsProcessesApartByMoreThanTheirId(): void
    {
        if (!is_dir('/proc/1')) {
            self::markTestSkipped('The system does not publish process start times.');
        }

        // Two processes that started at different times, so an id handed out
        // again cannot pass for the first one.
        self::assertNotSame('1-', HostProcess::identify(1));
        self::assertNotSame(HostProcess::identify(1), HostProcess::identify((int) getmypid()));
    }

    public function testAProcessThatIsGoneHasNoStartTime(): void
    {
        self::assertSame('2147483646-x', HostProcess::identify(2_147_483_646));
    }
}
