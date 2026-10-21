<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use PHPUnit\Framework\TestCase;

use function dirname;
use function fclose;
use function file_put_contents;
use function mkdir;
use function proc_close;
use function proc_open;
use function realpath;
use function stream_get_contents;
use function sys_get_temp_dir;
use function uniqid;
use function var_export;

use const PHP_BINARY;

final class WorkerEntrypointTest extends TestCase
{
    /** @var non-empty-string */
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mago-drupal-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        DiskCacheTest::remove($this->directory);
    }

    /**
     * Mago reads the worker's stdout as the protocol stream, so text that a
     * project's autoloader prints goes to stderr.
     */
    public function testAutoloaderOutputGoesToStderr(): void
    {
        $repository = (string) realpath(dirname(__DIR__, levels: 2));
        mkdir($this->directory . '/vendor/composer', recursive: true);
        file_put_contents(
            $this->directory . '/vendor/autoload.php',
            data: "<?php\necho \"printed by a bootstrap\\n\";\nreturn require "
            . var_export($repository . '/vendor/autoload.php', return: true)
            . ";\n",
        );
        file_put_contents(
            $this->directory . '/vendor/composer/autoload_psr4.php',
            data: "<?php\nreturn ['amateescu\\\\MagoDrupal\\\\' => ["
            . var_export($repository . '/src', return: true)
            . "]];\n",
        );

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, $repository . '/resources/worker.php'],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            cwd: $this->directory,
        );
        self::assertIsResource($process);
        [$stdin, $stdout, $stderr] = [$pipes[0] ?? null, $pipes[1] ?? null, $pipes[2] ?? null];
        self::assertIsResource($stdin);
        self::assertIsResource($stdout);
        self::assertIsResource($stderr);

        // A closed input stream makes the worker stop.
        fclose($stdin);
        $output = (string) stream_get_contents($stdout);
        $errors = (string) stream_get_contents($stderr);
        $status = proc_close($process);

        self::assertSame(0, $status, $errors);
        self::assertSame('', $output);
        self::assertSame("printed by a bootstrap\n", $errors);
    }
}
