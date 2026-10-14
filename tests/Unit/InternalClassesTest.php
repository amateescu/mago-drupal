<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ExtensionFiles;
use amateescu\MagoDrupal\Internal\InternalClasses;
use PHPUnit\Framework\TestCase;

use function dirname;

final class InternalClassesTest extends TestCase
{
    private static function fixtures(): string
    {
        return dirname(__DIR__) . '/fixtures/internal';
    }

    public function testListsNonFinalClassesTaggedInternal(): void
    {
        $classes = InternalClasses::fromFiles(ExtensionFiles::phpFileTree([self::fixtures()])[0]);

        // Sealed is final, Open marks a method, Prose and Inline only mention
        // the word, MarkedInterface is not a class.
        self::assertSame(
            [
                'Drupal\sample\Internal\Attributed',
                'Drupal\sample\Internal\MarkedBase',
                'Drupal\sample\Internal\Spread',
                'Drupal\sample\Nested\Deep',
            ],
            $classes->names(),
        );
        self::assertSame(4, $classes->count());
    }

    public function testWalksOnlyExistingDirectoriesForPhpFiles(): void
    {
        $files = ExtensionFiles::phpFileTree([self::fixtures() . '/nested', self::fixtures() . '/missing'])[0];

        self::assertSame([self::fixtures() . '/nested/Deep.php'], $files);
        self::assertSame(0, InternalClasses::fromFiles([self::fixtures() . '/missing/Gone.php'])->count());
    }
}
