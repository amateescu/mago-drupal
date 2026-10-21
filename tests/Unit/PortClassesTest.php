<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use PHPUnit\Framework\TestCase;

use function dirname;

/**
 * Keeps the generated port classes in step with the two maps.
 */
final class PortClassesTest extends TestCase
{
    public function testGeneratedClassesAreCurrent(): void
    {
        $classes = new PortClasses(dirname(__DIR__, levels: 2));
        foreach (PortClasses::GENERATED as $file) {
            self::assertSame(
                $classes->render($file),
                $classes->read($file),
                "{$file} is out of date. Run `just ports-gen`.",
            );
        }
    }
}
