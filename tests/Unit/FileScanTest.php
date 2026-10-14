<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DiskCache;
use amateescu\MagoDrupal\Internal\FileScan;
use ArrayObject;
use PHPUnit\Framework\TestCase;

use function basename;
use function file_get_contents;
use function file_put_contents;
use function mkdir;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

final class FileScanTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mago-drupal-scan-' . uniqid();
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        DiskCacheTest::remove($this->directory);
    }

    public function testReadsOnlyTheFilesThatAreNewOrChanged(): void
    {
        $a = $this->write('a.php', 'one', 1_000_000);
        $b = $this->write('b.php', 'two', 1_000_000);
        /** @var ArrayObject<int, string> $reads */
        $reads = new ArrayObject();
        $read = static function (string $path) use ($reads): string {
            $reads[] = basename($path);

            return (string) file_get_contents($path);
        };

        [$scan, $held] = FileScan::update(null, DiskCache::stamps([$a, $b]), $read);
        self::assertSame([$a => 'one', $b => 'two'], $held);
        self::assertSame(['a.php', 'b.php'], $reads->getArrayCopy());

        $this->write('b.php', 'three', 1_000_001);
        $c = $this->write('c.php', 'four', 1_000_000);
        $reads->exchangeArray([]);
        [$scan, $held] = FileScan::update($scan, DiskCache::stamps([$a, $b, $c]), $read);
        self::assertSame([$a => 'one', $b => 'three', $c => 'four'], $held);
        self::assertSame(['b.php', 'c.php'], $reads->getArrayCopy());

        // A file that left the list is dropped.
        $reads->exchangeArray([]);
        [$scan, $held] = FileScan::update($scan, DiskCache::stamps([$a, $c]), $read);
        self::assertSame([$a => 'one', $c => 'four'], $held);
        self::assertSame([], $reads->getArrayCopy());

        // A file written in the last seconds may still be changing, so it is
        // read again every time until it settles.
        touch($a);
        [$scan] = FileScan::update($scan, DiskCache::stamps([$a, $c]), $read);
        FileScan::update($scan, DiskCache::stamps([$a, $c]), $read);
        self::assertSame(['a.php', 'a.php'], $reads->getArrayCopy());
    }

    private function write(string $name, string $contents, int $mtime): string
    {
        $path = $this->directory . '/' . $name;
        file_put_contents($path, $contents);
        touch($path, $mtime);

        return $path;
    }
}
