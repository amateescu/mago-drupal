<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DeprecationTarget;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class DeprecationTargetTest extends TestCase
{
    public function testKeepsWhatTheTargetMajorRemoves(): void
    {
        $target = DeprecationTarget::major(12);

        self::assertTrue($target->keeps('in drupal:10.3.0 and is removed from drupal:11.0.0. Use b() instead.'));
        self::assertTrue($target->keeps('in drupal:11.2.0 and is removed from drupal:12.0.0. Use b() instead.'));
        self::assertFalse($target->keeps('in drupal:11.4.0 and is removed from drupal:13.0.0. Use b() instead.'));
    }

    /**
     * Only Drupal's own removal version counts.
     */
    public function testKeepsTextWithoutADrupalRemovalVersion(): void
    {
        $target = DeprecationTarget::major(12);

        self::assertTrue($target->keeps('in webform:6.3.0 and is removed from webform:7.0.0.'));
        self::assertTrue($target->keeps('since Symfony 7.4, use properties directly instead'));
        self::assertTrue($target->keeps(''));
    }

    public function testAllKeepsEverything(): void
    {
        $all = DeprecationTarget::all();

        self::assertTrue($all->isAll());
        self::assertFalse(DeprecationTarget::major(12)->isAll());
        self::assertTrue($all->keeps('in drupal:11.4.0 and is removed from drupal:13.0.0.'));
    }

    public function testRejectsAMajorBelowOne(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DeprecationTarget::major(0);
    }
}
