<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Cache\FileMemo;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;
use WeakReference;

use function gc_collect_cycles;
use function str_repeat;

/**
 * @mago-expect lint:too-many-methods
 */
final class FileMemoTest extends TestCase
{
    public function testTheSameTextKeepsTheValue(): void
    {
        $memo = new FileMemo();

        self::assertSame('first', $memo->get('<?php echo 1;', static fn(): string => 'first'));
        self::assertSame('first', $memo->get('<?php echo 1;', static fn(): string => 'again'));
    }

    /**
     * Each request decodes its own string, so an equal text in another
     * string still counts as the same file.
     */
    public function testAnEqualTextInAnotherStringKeepsTheValue(): void
    {
        $memo = new FileMemo();
        $text = str_repeat(string: 'a', times: 3);

        self::assertSame('first', $memo->get($text, static fn(): string => 'first'));
        self::assertSame('first', $memo->get('aaa', static fn(): string => 'again'));
    }

    public function testChangedTextBuildsAgain(): void
    {
        $memo = new FileMemo();

        self::assertSame('first', $memo->get('<?php echo 1;', static fn(): string => 'first'));
        self::assertSame('second', $memo->get('<?php echo 2;', static fn(): string => 'second'));
        self::assertSame('third', $memo->get('<?php echo 1;', static fn(): string => 'third'));
    }

    public function testTwoMemosKeepTheirOwnValues(): void
    {
        $tokens = new FileMemo();
        $scopes = new FileMemo();

        self::assertSame('tokens', $tokens->get('<?php echo 1;', static fn(): string => 'tokens'));
        self::assertSame('scopes', $scopes->get('<?php echo 1;', static fn(): string => 'scopes'));
        self::assertSame('tokens', $tokens->get('<?php echo 1;', static fn(): string => 'again'));
        self::assertSame('other', $scopes->get('<?php echo 2;', static fn(): string => 'other'));
        self::assertSame('tokens', $tokens->get('<?php echo 1;', static fn(): string => 'again'));
    }

    /**
     * Two snapshots of the same text can hold different nodes, so a value
     * keyed by the snapshot is built again for the second one.
     */
    public function testANewSnapshotOfTheSameTextBuildsAgain(): void
    {
        $memo = new FileMemo();
        $first = self::sourceFile('<?php echo 1;');
        $second = self::sourceFile('<?php echo 1;');

        self::assertSame('first', $memo->get($first, static fn(): string => 'first'));
        self::assertSame('first', $memo->get($first, static fn(): string => 'again'));
        self::assertSame('second', $memo->get($second, static fn(): string => 'second'));
    }

    /**
     * An analyzer hook that waits on the host lets another file's request
     * run in between, and the first file's values must still be there after.
     */
    public function testAMemoKeepingSnapshotsKeepsTheValuesOfEachSnapshot(): void
    {
        $memo = (new FileMemo())->keepingSnapshots();
        $first = self::sourceFile('<?php echo 1;');
        $second = self::sourceFile('<?php echo 2;');

        self::assertSame('a1', self::item($memo, $first, 1, 'a1'));
        self::assertSame('b1', self::item($memo, $second, 1, 'b1'));
        self::assertSame('a1', self::item($memo, $first, 1, 'again'));
        self::assertSame('a2', self::item($memo, $first, 2, 'a2'));
        self::assertSame('b1', self::item($memo, $second, 1, 'again'));
        self::assertSame('a2', self::item($memo, $first, 2, 'again'));
    }

    public function testAPlainMemoKeepsOnlyTheLastSnapshot(): void
    {
        $memo = new FileMemo();
        $first = self::sourceFile('<?php echo 1;');
        $second = self::sourceFile('<?php echo 2;');

        self::assertSame('a1', self::item($memo, $first, 1, 'a1'));
        self::assertSame('b1', self::item($memo, $second, 1, 'b1'));
        self::assertSame('again', self::item($memo, $first, 1, 'again'));
    }

    /**
     * A worker reads thousands of files, so the values of a snapshot have to
     * go when the snapshot does.
     */
    public function testAMemoKeepingSnapshotsLetsGoOfASnapshot(): void
    {
        $memo = (new FileMemo())->keepingSnapshots();
        $file = self::sourceFile('<?php echo 1;');
        $memo->get($file, static fn(): string => 'value');
        $memo->get(self::sourceFile('<?php echo 2;'), static fn(): string => 'next');
        $memo->get('<?php echo 3;', static fn(): string => 'text');

        $reference = WeakReference::create($file);
        unset($file);
        gc_collect_cycles();

        self::assertNull($reference->get());
    }

    public function testItemsAreKeptForTheLastText(): void
    {
        $memo = new FileMemo();

        self::assertSame('a1', self::item($memo, '<?php echo 1;', 1, 'a1'));
        self::assertSame('a2', self::item($memo, '<?php echo 1;', 2, 'a2'));
        self::assertSame('a1', self::item($memo, '<?php echo 1;', 1, 'again'));
        self::assertSame('b1', self::item($memo, '<?php echo 2;', 1, 'b1'));
        self::assertSame('c2', self::item($memo, '<?php echo 1;', 2, 'c2'));
    }

    public function testANullItemIsKept(): void
    {
        $memo = new FileMemo();
        $memo->keep('<?php', null, 1);

        self::assertTrue($memo->has('<?php', 1));
        self::assertNull($memo->value(1));
    }

    /**
     * A build that waits on the host lets another file's request switch the
     * memo, and the value it keeps must land with its own file.
     */
    public function testAValueKeptAfterAnotherFileTookTheMemoLandsWithItsFile(): void
    {
        $memo = (new FileMemo())->keepingSnapshots();
        $first = self::sourceFile('<?php echo 1;');
        $second = self::sourceFile('<?php echo 2;');

        self::assertFalse($memo->has($first, 1));
        self::assertSame('second', self::item($memo, $second, 1, 'second'));
        $memo->keep($first, 'first', 1);

        self::assertSame('second', self::item($memo, $second, 1, 'again'));
        self::assertSame('first', self::item($memo, $first, 1, 'again'));
    }

    /**
     * The item for the key, or $value when the memo doesn't hold one, which
     * it then keeps. The rules call the memo this way.
     *
     * @param FileMemo<string> $memo
     */
    private static function item(FileMemo $memo, string|SourceFile $key, int $item, string $value): string
    {
        return $memo->has($key, $item) ? $memo->value($item) : $memo->keep($key, $value, $item);
    }

    private static function sourceFile(string $contents): SourceFile
    {
        return new SourceFile(
            PHPVersion::fromParts(major: 8, minor: 1),
            'modules/corpus/corpus.module',
            $contents,
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore('', 0),
            null,
        );
    }
}
