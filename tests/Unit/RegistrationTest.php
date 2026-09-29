<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\DrupalExtension;
use Closure;
use Mago\Sdk\Analyzer\Plugin;
use Mago\Sdk\Analyzer\PluginRegistry;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;
use RegexIterator;
use SplObjectStorage;

use function array_diff;
use function array_filter;
use function array_intersect;
use function array_keys;
use function array_map;
use function array_merge;
use function array_values;
use function class_exists;
use function dirname;
use function getenv;
use function is_array;
use function is_object;
use function iterator_to_array;
use function preg_match;
use function putenv;
use function sort;
use function str_replace;
use function strlen;
use function substr;

/**
 * Every concrete check, hook, provider, filter and rule is registered.
 *
 * The test runs the real registration into the SDK's registry and walks what
 * got registered, into the checks the metadata hooks hold. A class that
 * nothing registers fails here instead of going quiet. A class with nothing
 * but static methods cannot be registered, so it counts as a helper.
 */
final class RegistrationTest extends TestCase
{
    /**
     * Classes with instance methods that the registered objects use without
     * registering them.
     */
    private const HELPERS = [
        // Built per request, around the context of the hook reporting.
        Reporter::class,
    ];

    /**
     * The directories under `src` whose concrete classes have to be
     * registered.
     */
    private const DIRECTORIES = ['Analyzer', 'Linter/Rules'];

    /**
     * The classes the walk records and looks into.
     */
    private const WALKED = '/^amateescu\\\\MagoDrupal\\\\(?:Analyzer|Linter)\\\\/';

    private string|false $cache = false;

    protected function setUp(): void
    {
        // Registration reads the root's indexes, which need no disk cache here.
        $this->cache = getenv('MAGO_DRUPAL_CACHE');
        putenv('MAGO_DRUPAL_CACHE=0');
    }

    protected function tearDown(): void
    {
        putenv('MAGO_DRUPAL_CACHE' . ($this->cache === false ? '' : '=' . $this->cache));
    }

    public function testRegistersEveryConcreteClass(): void
    {
        $unregistered = array_values(array_diff(self::concreteClasses(), self::registered(), self::HELPERS));

        self::assertSame([], $unregistered, 'Nothing registers these classes.');
    }

    /**
     * A helper that is registered, or does not exist, leaves the list.
     */
    public function testListsOnlyUnregisteredHelpers(): void
    {
        self::assertSame(self::HELPERS, array_values(array_filter(self::HELPERS, class_exists(...))));
        self::assertSame([], array_values(array_intersect(self::HELPERS, self::registered())));
    }

    /**
     * The classes the registration reaches, over both settings that decide
     * what gets registered: `--core` and a deprecation target. The corpus
     * root has the `@deprecated` and `@internal` symbols the conditional
     * hooks need.
     *
     * @return list<string>
     */
    private static function registered(): array
    {
        $root = dirname(__DIR__) . '/corpus';
        $reached = [];
        // Holds every object walked, so none is freed and its id reused.
        /** @var SplObjectStorage<object, null> $seen */
        $seen = new SplObjectStorage();
        foreach ([
            DrupalExtension::create(root: $root, deprecations: 12),
            DrupalExtension::create(core: true, root: $root),
        ] as $extension) {
            self::reach(
                [
                    $extension->linterRules,
                    $extension->analyzerPlugins,
                    array_map(self::registrations(...), $extension->analyzerPlugins),
                ],
                $reached,
                $seen,
            );
        }

        $classes = array_keys($reached);
        sort($classes);

        return $classes;
    }

    /**
     * Every list the registry keeps after the plugin registers, whatever
     * kind of hook it holds.
     *
     * @return list<mixed>
     */
    private static function registrations(Plugin $plugin): array
    {
        $registry = new PluginRegistry();
        $plugin->register($registry);

        return array_map(static fn(ReflectionProperty $property): mixed => $property->getValue(
            $registry,
        ), (new ReflectionObject($registry))->getProperties());
    }

    /**
     * Records the analyzer and linter objects in the value, and walks their
     * properties, where hooks hold their checks.
     *
     * @param array<string, true> $reached
     * @param SplObjectStorage<object, null> $seen
     */
    private static function reach(mixed $value, array &$reached, SplObjectStorage $seen): void
    {
        /** @var mixed $item */
        foreach (is_array($value) ? $value : [] as $item) {
            self::reach($item, $reached, $seen);
        }

        if (
            !is_object($value)
            || $value instanceof Closure
            || $seen->offsetExists($value)
            || preg_match(self::WALKED, $value::class) !== 1
        ) {
            return;
        }

        $seen->offsetSet($value);
        $reached[$value::class] = true;
        self::reach(
            array_map(static fn(ReflectionProperty $property): mixed => $property->isInitialized($value)
                ? $property->getValue($value)
                : null, (new ReflectionObject($value))->getProperties()),
            $reached,
            $seen,
        );
    }

    /**
     * The concrete classes declared under the directories.
     *
     * @return list<string>
     */
    private static function concreteClasses(): array
    {
        $source = dirname(__DIR__, levels: 2) . '/src/';
        $files = array_merge(...array_map(static fn(string $directory): array => array_keys(iterator_to_array(
            new RegexIterator(
                new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source . $directory)),
                '/\.php$/',
            ),
        )), self::DIRECTORIES));
        $classes = array_map(
            static fn(string $file): string => 'amateescu\MagoDrupal\\'
            . str_replace('/', replace: '\\', subject: substr($file, offset: strlen($source), length: -4)),
            $files,
        );
        $classes = array_values(array_filter($classes, self::registrable(...)));
        sort($classes);

        return $classes;
    }

    /**
     * Whether an instance of the class could be registered: it is a class,
     * not abstract or an enum, with an instance method besides the
     * constructor.
     */
    private static function registrable(string $class): bool
    {
        if (!class_exists($class)) {
            return false;
        }

        $reflection = new ReflectionClass($class);
        $instance = array_filter(
            $reflection->getMethods(),
            static fn(ReflectionMethod $method): bool => !$method->isStatic() && !$method->isConstructor(),
        );

        return !$reflection->isAbstract() && !$reflection->isEnum() && $instance !== [];
    }
}
