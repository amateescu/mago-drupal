<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ServiceParameters;
use amateescu\MagoDrupal\Internal\ServiceYaml;
use PHPUnit\Framework\TestCase;

use function dirname;

final class ServiceParametersTest extends TestCase
{
    public function testTypesTheKindOfEachValue(): void
    {
        $parameters = self::parameters();

        // The kernel replaces the empty string, but with another string.
        self::assertSame('string', (string) $parameters->type('app.root'));
        self::assertSame('int', (string) $parameters->type('limit'));
        self::assertSame('float', (string) $parameters->type('ratio'));
        // A mapping and a list get no shape, since other files may change it.
        self::assertSame('array<int|string, mixed>', (string) $parameters->type('options'));
        self::assertSame('array<int|string, mixed>', (string) $parameters->type('list'));
        // A placeholder inside a longer string, and an escaped `@`.
        self::assertSame('string', (string) $parameters->type('path'));
        self::assertSame('string', (string) $parameters->type('escaped'));
    }

    public function testFilesThatDisagreeGiveEveryKind(): void
    {
        $parameters = self::parameters();

        self::assertSame('int|string', (string) $parameters->type('either'));
        // Two values of one kind are one kind.
        self::assertSame('bool', (string) $parameters->type('flag'));
    }

    public function testValuesResolvedAtRuntimeKeepTheDeclaredType(): void
    {
        $parameters = self::parameters();

        foreach (['nothing', 'copy', 'service', 'tagged', 'not.defined', '7'] as $name) {
            self::assertNull($parameters->type($name), $name);
        }
    }

    public function testReadsServicesAndParametersInOneParse(): void
    {
        [$definitions, $kinds] = ServiceYaml::read([
            dirname(__DIR__) . '/fixtures/services/parameters/first.services.yml',
        ]);

        self::assertSame('Drupal\first\Thing', $definitions['thing']['class'] ?? null);
        self::assertSame(['bool' => true], $kinds['flag'] ?? null);
    }

    private static function parameters(): ServiceParameters
    {
        $directory = dirname(__DIR__) . '/fixtures/services/parameters';

        return new ServiceParameters(
            ServiceYaml::read([
                $directory . '/first.services.yml',
                $directory . '/second.services.yml',
            ])[1],
        );
    }
}
