<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ServiceClasses;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function file_put_contents;
use function is_dir;
use function mkdir;
use function rmdir;
use function scandir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class ServiceClassesTest extends TestCase
{
    private const SERVICES = <<<'YAML'
        parameters:
          mymodule.moved_classes:
            'Drupal\mymodule\OldName':
              class: 'Drupal\mymodule\Moved'
        services:
          mymodule.plain:
            class: Drupal\mymodule\Plain
          mymodule.quoted:
            class: '\Drupal\mymodule\Quoted'
          mymodule.escaped:
            class: "Drupal\\mymodule\\Escaped"
          mymodule.arguments:
            class: Drupal\mymodule\WithArguments # A comment.
            arguments: ['@entity_type.manager']
          Drupal\mymodule\Autowired: ~
          'Drupal\mymodule\QuotedName':
            autowire: true
        YAML;

    private static string $root = '';

    public static function setUpBeforeClass(): void
    {
        self::$root = sys_get_temp_dir() . '/' . uniqid(prefix: 'service-classes-');
        mkdir(self::$root . '/mymodule/src/Form', recursive: true);
        mkdir(self::$root . '/other');
        file_put_contents(self::$root . '/mymodule/mymodule.services.yml', self::SERVICES);
        file_put_contents(
            self::$root . '/mymodule/mymodule.extra.services.yml',
            data: "services:\n  mymodule.extra:\n    class: Drupal\\mymodule\\Extra\n",
        );
    }

    public static function tearDownAfterClass(): void
    {
        self::remove(self::$root);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function classes(): iterable
    {
        yield 'class key' => ['Drupal\mymodule\Plain', true];
        yield 'single-quoted class with a leading backslash' => ['Drupal\mymodule\Quoted', true];
        yield 'double-quoted class' => ['Drupal\mymodule\Escaped', true];
        yield 'class followed by a comment' => ['Drupal\mymodule\WithArguments', true];
        yield 'service named after its class' => ['Drupal\mymodule\Autowired', true];
        yield 'quoted service name' => ['Drupal\mymodule\QuotedName', true];
        yield 'class of the longer file name' => ['Drupal\mymodule\Extra', false];
        yield 'class that is not a service' => ['Drupal\mymodule\Missing', false];
        yield 'class under parameters' => ['Drupal\mymodule\Moved', false];
        yield 'class name key under parameters' => ['Drupal\mymodule\OldName', false];
    }

    #[DataProvider('classes')]
    public function testReadsTheNearestServicesFile(string $class, bool $service): void
    {
        self::assertSame($service, ServiceClasses::has(self::$root . '/mymodule/src/Form/Foo.php', $class));
    }

    public function testFindsNothingOutsideAModule(): void
    {
        self::assertFalse(ServiceClasses::has(self::$root . '/other/Foo.php', 'Drupal\mymodule\Plain'));
    }

    /**
     * Deletes a directory with its contents.
     */
    private static function remove(string $path): void
    {
        if (!is_dir($path)) {
            unlink($path);

            return;
        }

        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            self::remove($path . '/' . (string) $entry);
        }

        rmdir($path);
    }
}
