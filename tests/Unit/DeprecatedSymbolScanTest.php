<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DeprecatedSymbolScan;
use PHPUnit\Framework\TestCase;

use function dirname;

final class DeprecatedSymbolScanTest extends TestCase
{
    private const CLASS_NAME = 'Drupal\fixture\NamedClassArgument';

    public function testAClassNamedArgumentIsNotADeclaration(): void
    {
        $symbols = DeprecatedSymbolScan::files([dirname(__DIR__) . '/fixtures/deprecated/NamedClassArgument.php']);

        self::assertNotNull($symbols->constant(self::CLASS_NAME, 'BEFORE'));
        // Members after the call still belong to the class.
        self::assertNotNull($symbols->constant(self::CLASS_NAME, 'AFTER'));
        self::assertNotNull($symbols->property(self::CLASS_NAME, 'legacy'));
    }
}
