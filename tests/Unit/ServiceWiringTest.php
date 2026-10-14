<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ServiceArguments;
use amateescu\MagoDrupal\Internal\ServiceWiring;
use amateescu\MagoDrupal\Internal\ServiceYaml;
use PHPUnit\Framework\TestCase;

use function array_map;

final class ServiceWiringTest extends TestCase
{
    public function testCollectsCallsWithTheParentsOnes(): void
    {
        $wiring = ServiceWiring::fromDefinitions([
            'base' => ['abstract' => true, 'calls' => [['setCacheBackend', ['@cache.discovery', 'key']]]],
            'manager' => [
                'class' => 'Drupal\foo\Manager',
                'parent' => 'base',
                'calls' => [['method' => 'alterInfo', 'arguments' => ['foo_info']]],
            ],
            'other' => ['class' => 'Drupal\foo\Other', 'calls' => 'not a list'],
        ]);

        self::assertSame(['alterinfo' => true, 'setcachebackend' => true], $wiring->calls('drupal\foo\manager'));
        self::assertSame([], $wiring->calls('Drupal\foo\Other'));
        self::assertSame([], $wiring->calls('Drupal\foo\Missing'));
    }

    public function testCountsArgumentsTheWayTheContainerMergesThem(): void
    {
        $wiring = ServiceWiring::fromDefinitions(self::fromFile([
            'plain' => ['class' => 'Drupal\foo\Plain', 'arguments' => ['@a', '%b%', 'c']],
            'bare' => ['class' => 'Drupal\foo\Bare'],
            'base' => ['abstract' => true, 'arguments' => ['@a', '@b']],
            'appended' => ['class' => 'Drupal\foo\Appended', 'parent' => 'base', 'arguments' => ['@c']],
            'replaced' => ['class' => 'Drupal\foo\Replaced', 'parent' => 'base', 'arguments' => ['index_1' => '@c']],
            // Numeric keys append, so the index counts what came before it.
            'both' => ['class' => 'Drupal\foo\Both', 'parent' => 'base', 'arguments' => [0 => '@c', 'index_2' => '@d']],
            'grandchild' => ['class' => 'Drupal\foo\Grandchild', 'parent' => 'appended', 'arguments' => ['@d']],
            'middleware' => [
                'class' => 'Drupal\foo\Middleware',
                'arguments' => ['@a'],
                'tags' => [['name' => 'http_middleware', 'priority' => 5], 'event_subscriber'],
            ],
            'collector' => ['class' => 'Drupal\foo\Collector', 'tags' => [['name' => 'service_id_collector']]],
            'proxy' => ['class' => 'Drupal\foo\Proxy', 'tags' => ['session_handler_proxy']],
        ]));

        $counts = [
            'plain' => 3,
            'bare' => 0,
            'appended' => 3,
            'replaced' => 2,
            'both' => 3,
            'grandchild' => 4,
            'middleware' => 2,
            'collector' => 1,
            'proxy' => 1,
        ];
        foreach ($counts as $id => $count) {
            $class = 'Drupal\foo\\' . ucfirst($id);
            self::assertSame([$id => $count], self::counts($wiring->arguments($class)), $id);
            self::assertTrue($wiring->hasArguments($class), $id);
        }

        self::assertSame('sample.services.yml', $wiring->arguments('Drupal\foo\Plain')[0]->file);
    }

    public function testLeavesOutWhatTheFileDoesNotDecide(): void
    {
        $wiring = ServiceWiring::fromDefinitions([
            ...self::fromFile([
                'autowired' => ['class' => 'Drupal\foo\Autowired', 'autowire' => true],
                'autowired_base' => ['abstract' => true, 'autowire' => true],
                'autowired_child' => ['class' => 'Drupal\foo\AutowiredChild', 'parent' => 'autowired_base'],
                'named' => ['class' => 'Drupal\foo\Named', 'arguments' => ['$first' => '@a']],
                'keyed' => ['class' => 'Drupal\foo\Keyed', 'arguments' => [1 => '@a']],
                'factory' => ['class' => 'Drupal\foo\Factory', 'factory' => ['@a', 'make']],
                'factory_base' => ['abstract' => true, 'factory' => 'Drupal\foo\Make::make'],
                'factory_child' => ['class' => 'Drupal\foo\FactoryChild', 'parent' => 'factory_base'],
                'synthetic' => ['class' => 'Drupal\foo\Synthetic', 'synthetic' => true],
                'abstract' => ['class' => 'Drupal\foo\AbstractOne', 'abstract' => true],
                'out_of_range' => [
                    'class' => 'Drupal\foo\OutOfRange',
                    'parent' => 'bare',
                    'arguments' => ['index_0' => '@a'],
                ],
                'bare' => ['class' => 'Drupal\foo\Bare'],
                'orphan' => ['class' => 'Drupal\foo\Orphan', 'parent' => 'missing'],
                'string_arguments' => ['class' => 'Drupal\foo\StringArguments', 'arguments' => '@a'],
                'alias' => '@bare',
                'provided_base' => ['class' => 'Drupal\foo\ProvidedBase'],
            ]),
            // A service provider's definition has no services file.
            'provided' => ['class' => 'Drupal\foo\Provided'],
            'provided_parent' => ['abstract' => true],
            'provided_child' => [
                'class' => 'Drupal\foo\ProvidedChild',
                'parent' => 'provided_parent',
                ServiceYaml::MODULE => 'sample',
            ],
        ]);

        foreach ([
            'Autowired',
            'AutowiredChild',
            'Named',
            'Keyed',
            'Factory',
            'FactoryChild',
            'Synthetic',
            'AbstractOne',
            'OutOfRange',
            'Orphan',
            'StringArguments',
            'Provided',
            'ProvidedChild',
        ] as $class) {
            self::assertFalse($wiring->hasArguments('Drupal\foo\\' . $class), $class);
        }

        // The alias is the same service, not a second one.
        self::assertSame(['bare' => 0], self::counts($wiring->arguments('Drupal\foo\Bare')));
    }

    public function testFileDefaultsAutowireTheirServices(): void
    {
        $definitions = ServiceYaml::definitions([
            'services' => [
                '_defaults' => ['autowire' => true],
                'defaulted' => ['class' => 'Drupal\foo\Defaulted'],
                'opted_out' => ['class' => 'Drupal\foo\OptedOut', 'autowire' => false, 'arguments' => ['@a']],
            ],
        ]);
        $wiring = ServiceWiring::fromDefinitions(self::fromFile($definitions));

        self::assertFalse($wiring->hasArguments('Drupal\foo\Defaulted'));
        self::assertSame(['opted_out' => 1], self::counts($wiring->arguments('Drupal\foo\OptedOut')));
    }

    /**
     * Marks the definitions as read from `sample.services.yml`.
     *
     * @param array<non-empty-string, array<array-key, mixed>|string> $definitions
     * @return array<non-empty-string, array<array-key, mixed>|string>
     */
    private static function fromFile(array $definitions): array
    {
        return array_map(static fn(array|string $definition): array|string => (
            is_array($definition) ? [...$definition, ServiceYaml::MODULE => 'sample'] : $definition
        ), $definitions);
    }

    /**
     * @param list<ServiceArguments> $services
     * @return array<string, int>
     */
    private static function counts(array $services): array
    {
        $counts = [];
        foreach ($services as $service) {
            $counts[$service->id] = $service->count;
        }

        return $counts;
    }
}
