<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Cache\DiskCache;
use amateescu\MagoDrupal\Internal\Traits\TraitComposers;
use amateescu\MagoDrupal\Internal\Traits\TraitComposerScan;
use PHPUnit\Framework\TestCase;

use function basename;
use function copy;
use function dirname;
use function file_put_contents;
use function glob;
use function mkdir;
use function sys_get_temp_dir;
use function touch;
use function uniqid;
use function unlink;

/**
 * The result is kept while the changed files don't name any of the traits,
 * and matches a new scan whenever one does.
 */
final class TraitComposerScanTest extends TestCase
{
    private const TRAIT = 'Drupal\fixture\Serializing';

    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mago-drupal-composers-' . uniqid();
        mkdir($this->directory);
        $fixtures = glob(dirname(__DIR__) . '/fixtures/traits/*.php');
        foreach ($fixtures === false ? [] : $fixtures as $fixture) {
            $copy = $this->directory . '/' . basename($fixture);
            copy($fixture, $copy);
            touch($copy, mtime: 1_000_000);
        }
    }

    protected function tearDown(): void
    {
        DiskCacheTest::remove($this->directory);
    }

    public function testKeepsTheResultUntilAChangedFileNamesATrait(): void
    {
        $scan = $this->update(null);
        self::assertEquals(TraitComposers::fromFiles($this->files(), self::TRAIT), $scan->composers);

        // Another trait of the same short name counts as a mention, as in the
        // scan, so the edit is made to a file that doesn't name any of them.
        $this->write('Other.php', "<?php\n\nnamespace Drupal\\fixture;\n\nclass Other {}\n");
        $kept = $this->update($scan);
        self::assertSame($scan->composers, $kept->composers);

        $this->write('Late.php', "<?php\n\nnamespace Drupal\\fixture;\n\nclass Late\n{\n    use Nested;\n}\n");
        $grown = $this->update($kept);
        self::assertEquals(TraitComposers::fromFiles($this->files(), self::TRAIT), $grown->composers);
        self::assertContains('Drupal\fixture\Late', $grown->composers->classes);

        // A file the scan read is gone.
        unlink($this->directory . '/Late.php');
        $shrunk = $this->update($grown);
        self::assertEquals(TraitComposers::fromFiles($this->files(), self::TRAIT), $shrunk->composers);
        self::assertNotContains('Drupal\fixture\Late', $shrunk->composers->classes);
    }

    private function update(?TraitComposerScan $earlier): TraitComposerScan
    {
        $files = $this->files();

        return TraitComposerScan::update($earlier, $files, DiskCache::stamps($files), self::TRAIT);
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = glob($this->directory . '/*.php');

        return $files === false ? [] : $files;
    }

    private function write(string $name, string $contents): void
    {
        file_put_contents($this->directory . '/' . $name, $contents);
        touch($this->directory . '/' . $name, mtime: 1_000_001);
    }
}
