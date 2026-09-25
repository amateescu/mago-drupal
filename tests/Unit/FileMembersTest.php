<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\FileMembers;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;
use WeakReference;

use function gc_collect_cycles;

final class FileMembersTest extends TestCase
{
    /**
     * A worker analyzes thousands of files, so the cache entry for one has to
     * go when its snapshot does. It only does while nothing in the entry
     * points back at the snapshot: PHP 8.1 and 8.2 never collect a weak map
     * entry whose value references its own key.
     */
    public function testTheCacheLetsGoOfASnapshot(): void
    {
        $file = self::sourceFile();
        FileMembers::of($file);

        $reference = WeakReference::create($file);
        unset($file);
        gc_collect_cycles();

        self::assertNull($reference->get());
    }

    public function testOneSnapshotGetsOneEntry(): void
    {
        $file = self::sourceFile();

        self::assertSame(FileMembers::of($file), FileMembers::of($file));
        self::assertNotSame(FileMembers::of($file), FileMembers::of(self::sourceFile()));
    }

    private static function sourceFile(): SourceFile
    {
        return new SourceFile(
            PHPVersion::fromParts(major: 8, minor: 1),
            'modules/corpus/src/Thing.php',
            '<?php',
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore('', 0),
            null,
        );
    }
}
