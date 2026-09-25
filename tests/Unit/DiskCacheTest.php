<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DiskCache;
use amateescu\MagoDrupal\Internal\HookFunctions;
use ErrorException;
use PHPUnit\Framework\TestCase;
use Revolt\EventLoop;

use function chmod;
use function file_put_contents;
use function fileperms;
use function glob;
use function is_dir;
use function is_file;
use function is_link;
use function microtime;
use function mkdir;
use function proc_close;
use function proc_open;
use function putenv;
use function rename;
use function restore_error_handler;
use function rmdir;
use function scandir;
use function set_error_handler;
use function strlen;
use function substr;
use function symlink;
use function sys_get_temp_dir;
use function time;
use function touch;
use function uniqid;
use function unlink;
use function usleep;
use function var_export;

use const PHP_BINARY;

/**
 * @mago-expect lint:too-many-methods
 */
final class DiskCacheTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mago-drupal-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        self::remove($this->directory);
    }

    public static function remove(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = glob($directory . '/*');
        foreach ($entries === false ? [] : $entries as $entry) {
            if (is_dir($entry) && !is_link($entry)) {
                self::remove($entry);
                continue;
            }

            unlink($entry);
        }

        rmdir($directory);
    }

    public function testStoresOneEntryPerKindAndReadsItBack(): void
    {
        $cache = new DiskCache($this->directory);
        self::assertNull($cache->get('services', 'a'));

        $cache->set('services', 'a', ['x' => ['class' => 'X']]);
        self::assertSame(['x' => ['class' => 'X']], $cache->get('services', 'a'));

        $cache->set('services', 'b', ['y' => []]);
        self::assertNull($cache->get('services', 'a'));
        self::assertSame(['y' => []], $cache->get('services', 'b'));
    }

    public function testOnlyAllowedClassesComeBackAsObjects(): void
    {
        $cache = new DiskCache($this->directory);
        $cache->set('hooks', 'a', HookFunctions::fromDefinitions(['hook_x' => [true, 2]]));

        /** @var mixed $hooks */
        $hooks = $cache->get('hooks', 'a', [HookFunctions::class]);
        self::assertInstanceOf(HookFunctions::class, $hooks);
        self::assertTrue($hooks->deprecation('hook_x'));
        self::assertNotInstanceOf(HookFunctions::class, $cache->get('hooks', 'a'));
    }

    public function testFingerprintFollowsTheFileContents(): void
    {
        mkdir($this->directory);
        $file = $this->directory . '/a.yml';
        file_put_contents($file, data: 'a: 1');
        touch($file, mtime: 1_000_000);
        $before = DiskCache::fingerprint([$file]);
        self::assertSame($before, DiskCache::fingerprint([$file]));

        file_put_contents($file, data: 'a: 12');
        touch($file, mtime: 1_000_000);
        self::assertNotSame($before, DiskCache::fingerprint([$file]));

        // A file written within the last seconds may still be changing, so
        // nothing is keyed on it.
        touch($file);
        self::assertNull(DiskCache::fingerprint([$file]));
    }

    public function testSharedBuildsOncePerRun(): void
    {
        $cache = new DiskCache($this->directory);
        $builds = new \ArrayObject([]);
        $build = static function () use ($builds): HookFunctions {
            $builds->append(1);

            return HookFunctions::fromDefinitions(['hook_y' => [false, 1]]);
        };

        $first = $cache->shared('hooks', '1-0', $build, [HookFunctions::class]);
        $second = $cache->shared('hooks', '1-0', $build, [HookFunctions::class]);
        self::assertCount(1, $builds);
        self::assertSame(1, $first->parameterCount('hook_y'));
        self::assertSame(1, $second->parameterCount('hook_y'));

        $cache->shared('hooks', '1-1', $build, [HookFunctions::class]);
        self::assertCount(2, $builds);
    }

    public function testARunEntryLeftBehindByAnOlderRunIsNotRead(): void
    {
        $cache = new DiskCache($this->directory);
        $cache->shared(
            'hooks',
            '1-old',
            static fn(): HookFunctions => HookFunctions::fromDefinitions(['hook_old' => [false, 1]]),
            [HookFunctions::class],
        );

        // A host process id handed out again would name the same entry.
        touch($this->entry('hooks', 'run-1-old'), mtime: time() - 7_200);
        $built = $cache->shared(
            'hooks',
            '1-old',
            static fn(): HookFunctions => HookFunctions::fromDefinitions(['hook_new' => [false, 2]]),
            [HookFunctions::class],
        );

        self::assertSame(2, $built->parameterCount('hook_new'));
        self::assertNull($built->parameterCount('hook_old'));
    }

    public function testWaitingForAnotherWorkerLeavesTheEventLoopRunning(): void
    {
        mkdir($this->directory);
        $lock = $this->entry('hooks', 'run-1-held') . '.lock';
        $ready = $this->directory . '/held';
        $holder = self::holdLock($lock, $ready);

        $fired = false;
        EventLoop::delay(0.01, static function () use (&$fired): void {
            $fired = true;
        });

        $cache = new DiskCache($this->directory);
        $built = $cache->shared(
            'hooks',
            '1-held',
            static fn(): HookFunctions => HookFunctions::fromDefinitions(['hook_z' => [false, 3]]),
            [HookFunctions::class],
        );

        proc_close($holder);
        // A blocking lock would stop the loop, and with it every response the
        // build in the other worker is waiting for.
        self::assertTrue($fired);
        self::assertSame(3, $built->parameterCount('hook_z'));
    }

    /**
     * Starts a process that takes the lock, reports it, and holds it briefly.
     *
     * @return resource
     */
    private static function holdLock(string $lock, string $ready)
    {
        $code =
            '$h = fopen('
            . var_export($lock, return: true)
            . ', "c");'
            . 'flock($h, LOCK_EX);'
            . 'touch('
            . var_export($ready, return: true)
            . ');'
            . 'usleep(200000);';
        $pipes = [];
        $process = proc_open([PHP_BINARY, '-r', $code], [], $pipes);
        self::assertIsResource($process);

        $deadline = microtime(as_float: true) + 10.0;
        while (!is_file($ready)) {
            // Fail only at the deadline. An assertion on every pass makes the
            // assertion count depend on how fast the process starts.
            if (microtime(as_float: true) > $deadline) {
                self::fail('The lock holder never started.');
            }

            usleep(1000);
        }

        return $process;
    }

    public function testDropsRunEntriesOfFinishedHostProcesses(): void
    {
        $cache = new DiskCache($this->directory);
        $build = static fn(): HookFunctions => HookFunctions::fromDefinitions([]);
        $cache->shared('hooks', '999999-dead', $build, [HookFunctions::class]);
        self::assertNotNull($cache->get('hooks', 'run-999999-dead', [HookFunctions::class]));

        $cache->shared('hooks', '1-alive', $build, [HookFunctions::class]);
        self::assertNull($cache->get('hooks', 'run-999999-dead', [HookFunctions::class]));
        self::assertNotNull($cache->get('hooks', 'run-1-alive', [HookFunctions::class]));
    }

    /**
     * A worker killed between the write and the rename, or a write that ran
     * out of disk, leaves a temporary file. The next write of the kind drops
     * those of processes that are gone and those too old to be in progress.
     */
    public function testDropsTemporariesNoWriteWillFinish(): void
    {
        $cache = new DiskCache($this->directory);
        $cache->set('hooks', 'a', ['x']);
        $dead = $this->entry('hooks', 'a') . '.999999.tmp';
        $old = $this->entry('hooks', 'a') . '.1.tmp';
        $writing = $this->entry('hooks', 'b') . '.1.tmp';
        touch($dead);
        touch($old, mtime: time() - 3_600);
        touch($writing);

        $cache->set('hooks', 'b', ['y']);
        self::assertFileDoesNotExist($dead);
        self::assertFileDoesNotExist($old);
        self::assertFileExists($writing);
        self::assertSame(['y'], $cache->get('hooks', 'b'));
    }

    /**
     * A watch session writes an entry per analysis generation; the older ones
     * of the same host go, another host's stay, and so does nothing older
     * than a run can read.
     */
    public function testDropsEarlierGenerationsAndStaleRunEntries(): void
    {
        $cache = new DiskCache($this->directory);
        $build = static fn(): HookFunctions => HookFunctions::fromDefinitions([]);
        // Written first, so writing generation 1 does not remove it.
        $cache->shared('hooks', '1-100-g5-abc', $build, [HookFunctions::class]);
        $cache->shared('hooks', '1-100-g1-abc', $build, [HookFunctions::class]);
        $cache->shared('hooks', '1-200-g1-abc', $build, [HookFunctions::class]);
        self::assertFileExists($this->entry('hooks', 'run-1-100-g1-abc'));
        touch($this->entry('hooks', 'run-1-200-g1-abc'), mtime: time() - 7_200);

        $cache->shared('hooks', '1-100-g2-abc', $build, [HookFunctions::class]);

        self::assertFileDoesNotExist($this->entry('hooks', 'run-1-100-g1-abc'));
        self::assertFileDoesNotExist($this->entry('hooks', 'run-1-200-g1-abc'));
        self::assertFileExists($this->entry('hooks', 'run-1-100-g5-abc'));
        self::assertFileExists($this->entry('hooks', 'run-1-100-g2-abc'));
    }

    /**
     * An entry another version of the package wrote is never read, and the
     * next write of its kind sweeps it away.
     */
    public function testIgnoresEntriesOfOtherCode(): void
    {
        $cache = new DiskCache($this->directory);
        $cache->set('hooks', 'a', ['current']);
        $foreign = $this->directory . '/hooks-a-000000000000.cache';
        rename($this->entry('hooks', 'a'), $foreign);

        self::assertNull($cache->get('hooks', 'a'));

        $cache->set('hooks', 'a', ['current']);
        self::assertFileDoesNotExist($foreign);
        self::assertSame(['current'], $cache->get('hooks', 'a'));
    }

    /**
     * Another local user could create the shared temporary directory first,
     * or plant entries in one this user left open. A link is not used at
     * all; an open directory of this user's is closed and emptied first.
     */
    public function testTakesBackAnOpenDirectoryAndRefusesALink(): void
    {
        mkdir($this->directory, permissions: 0o700);
        $before = new DiskCache($this->directory . '/root', owned: $this->directory);
        $before->set('hooks', 'a', ['planted']);
        chmod($this->directory, permissions: 0o777);
        touch($this->directory . '/.planted');
        symlink($this->directory . '/root', $this->directory . '/link');

        $open = new DiskCache($this->directory . '/root', owned: $this->directory);
        self::assertNull($open->get('hooks', 'a'));
        self::assertSame(0o700, fileperms($this->directory) & 0o777);
        self::assertSame(['.', '..'], scandir($this->directory));
        $open->set('hooks', 'a', ['x']);
        self::assertSame(['x'], $open->get('hooks', 'a'));

        $link = $this->directory . '-link';
        symlink($this->directory, $link);
        try {
            $linked = new DiskCache($link . '/root', owned: $link);
            $linked->set('schema', 'a', ['y']);
            self::assertSame([], glob($this->directory . '/root/schema-*'));
            self::assertNull($linked->get('hooks', 'a'));
        } finally {
            unlink($link);
        }
    }

    /**
     * Workers race each other on the same files, and a warning printed while
     * a worker registers its plugins would land on the protocol stream.
     */
    public function testRacesPrintNothing(): void
    {
        $cache = new DiskCache($this->directory);
        $cache->set('hooks', 'a', ['x']);
        file_put_contents($this->entry('hooks', 'a'), data: 'not a serialized value');

        set_error_handler(static function (int $level, string $message): never {
            throw new ErrorException($message, severity: $level);
        });
        try {
            self::assertNotNull(DiskCache::fingerprint([$this->directory . '/gone.yml']));
            self::assertNull($cache->get('hooks', 'a'));
            self::assertNull($cache->get('hooks', 'missing'));
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The file an entry lives in. Entry names end in a hash of the package's
     * code, which a probe entry shows.
     */
    private function entry(string $kind, string $key): string
    {
        (new DiskCache($this->directory))->set('probe', 'x', true);
        $prefix = $this->directory . '/probe-x-';
        $probes = glob($prefix . '*.cache');
        self::assertIsArray($probes);
        self::assertCount(1, $probes);
        unlink($probes[0]);
        $code = substr($probes[0], offset: strlen($prefix), length: -strlen('.cache'));

        return $this->directory . '/' . $kind . '-' . $key . '-' . $code . '.cache';
    }

    public function testScopesRootsIntoTheirOwnDirectories(): void
    {
        $cache = new DiskCache($this->directory);
        $cache->scoped('/a/web')->set('walk', 'root', ['a']);
        $cache->scoped('/b/web')->set('walk', 'root', ['b']);

        self::assertSame(['a'], $cache->scoped('/a/web')->get('walk', 'root'));
        self::assertSame(['b'], $cache->scoped('/b/web')->get('walk', 'root'));
    }

    public function testReadsTheEnvironment(): void
    {
        putenv('MAGO_DRUPAL_CACHE=0');
        self::assertNull(DiskCache::fromEnvironment());

        putenv('MAGO_DRUPAL_CACHE=' . $this->directory);
        $cache = DiskCache::fromEnvironment();
        self::assertNotNull($cache);
        $cache->set('hooks', 'a', ['x']);
        self::assertFileExists($this->entry('hooks', 'a'));

        putenv('MAGO_DRUPAL_CACHE');
        self::assertNotNull(DiskCache::fromEnvironment());
    }
}
