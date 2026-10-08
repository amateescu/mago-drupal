<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\TraitComposers;
use PHPUnit\Framework\TestCase;

use function dirname;
use function glob;
use function sort;

final class TraitComposersTest extends TestCase
{
    public function testFindsEveryClassComposingTheTrait(): void
    {
        $files = glob(dirname(__DIR__) . '/fixtures/traits/*.php');
        $composers = TraitComposers::fromFiles($files === false ? [] : $files, 'Drupal\fixture\Serializing');
        $classes = $composers->classes;
        sort($classes);

        // By name, under an alias, and through a trait that uses it; not a
        // class that only names it, nor one using a trait of the same short
        // name elsewhere.
        self::assertSame(['Drupal\fixture\Aliased', 'Drupal\fixture\Direct', 'Drupal\fixture\ThroughNested'], $classes);
        self::assertSame(['Drupal\fixture\Nested'], $composers->traits);
    }
}
