<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\TrustedCallbackClasses;
use PHPUnit\Framework\TestCase;

final class TrustedCallbackClassesTest extends TestCase
{
    public function testReadsTheClassesAndMethodsCarryingTheAttribute(): void
    {
        $found = TrustedCallbackClasses::fromFiles([
            __DIR__ . '/../fixtures/trusted/Callbacks.php',
            __DIR__ . '/../fixtures/trusted/missing.php',
        ]);

        // A class that only implements the interface is not one, and an
        // anonymous class has no name to target. A trait is kept apart.
        self::assertSame(['Drupal\fixture\Imported', 'Drupal\fixture\FullyQualified'], $found->classes);
        self::assertSame(['Drupal\fixture\Shared'], $found->traits);
        self::assertSame(['prerender', 'lazybuilder', 'fromtrait'], $found->methods);
    }
}
