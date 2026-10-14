<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\SourceFileSet;
use PHPUnit\Framework\TestCase;

use function mkdir;
use function symlink;
use function sys_get_temp_dir;
use function touch;
use function uniqid;

final class SourceFileSetTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/mago-drupal-test-' . uniqid();
    }

    protected function tearDown(): void
    {
        DiskCacheTest::remove($this->directory);
    }

    public function testListsPhpFilesBelowEveryDirectory(): void
    {
        mkdir($this->directory . '/alpha/src/Nested', recursive: true);
        touch($this->directory . '/alpha/src/One.php');
        touch($this->directory . '/alpha/src/Nested/Two.php');
        touch($this->directory . '/alpha/src/notes.txt');
        touch($this->directory . '/alpha/src', mtime: 1_000_000);
        touch($this->directory . '/alpha/src/Nested', mtime: 1_000_000);
        $set = SourceFileSet::of([$this->directory . '/alpha/src', $this->directory . '/beta/src']);

        self::assertSame(
            [$this->directory . '/alpha/src/Nested/Two.php', $this->directory . '/alpha/src/One.php'],
            $set->files,
        );
        self::assertTrue($set->isCurrent($set->candidates));
    }

    /**
     * A symlink back up the tree is followed once, not once per level.
     */
    public function testListsEachFileOnceThroughALinkLoop(): void
    {
        mkdir($this->directory . '/alpha/src/Nested', recursive: true);
        touch($this->directory . '/alpha/src/One.php');
        symlink($this->directory . '/alpha/src', $this->directory . '/alpha/src/Nested/loop');
        $set = SourceFileSet::of([$this->directory . '/alpha/src']);

        self::assertSame([$this->directory . '/alpha/src/One.php'], $set->files);
    }

    public function testADirectoryWrittenJustNowIsWalkedAgain(): void
    {
        mkdir($this->directory . '/alpha/src', recursive: true);
        $set = SourceFileSet::of([$this->directory . '/alpha/src']);

        // A modification time counts seconds, so a file added right after the
        // listing would leave it unchanged.
        self::assertFalse($set->isCurrent($set->candidates));
    }

    public function testADirectoryTheWalkWouldReadDifferentlyIsNotCurrent(): void
    {
        mkdir($this->directory . '/alpha/src', recursive: true);
        touch($this->directory . '/alpha/src', mtime: 1_000_000);
        $set = SourceFileSet::of([$this->directory . '/alpha/src']);

        touch($this->directory . '/alpha/src/One.php');
        self::assertFalse($set->isCurrent($set->candidates));
    }

    public function testADirectoryThatAppearsIsNotCurrent(): void
    {
        $set = SourceFileSet::of([$this->directory . '/alpha/src']);
        self::assertSame([], $set->files);

        mkdir($this->directory . '/alpha/src', recursive: true);
        self::assertFalse($set->isCurrent($set->candidates));
    }

    public function testAnotherDirectoryToStartFromIsNotCurrent(): void
    {
        mkdir($this->directory . '/alpha/src', recursive: true);
        $set = SourceFileSet::of([$this->directory . '/alpha/src']);

        self::assertFalse($set->isCurrent([$this->directory . '/alpha/src', $this->directory . '/beta/src']));
        self::assertFalse($set->isCurrent([]));
    }
}
