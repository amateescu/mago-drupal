<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Deprecation\DeprecatedSymbolScan;
use PHPUnit\Framework\TestCase;

use function dirname;

final class DeprecatedSymbolScanTest extends TestCase
{
    private const CLASS_NAME = 'Drupal\fixture\NamedClassArgument';

    public function testAClassNamedArgumentIsNotADeclaration(): void
    {
        $symbols = DeprecatedSymbolScan::merge([
            DeprecatedSymbolScan::file(dirname(__DIR__) . '/fixtures/deprecated/NamedClassArgument.php'),
        ]);

        // A method after the argument and a property hook's braces still
        // belongs to the interface.
        self::assertNotNull($symbols->method(self::CLASS_NAME, 'legacy'));
    }
}
