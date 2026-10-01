<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DocblockRow;
use amateescu\MagoDrupal\Internal\DocblockRows;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;

use function array_map;
use function strpos;
use function substr;

final class DocblockRowsTest extends TestCase
{
    public function testSplitsLinesAtTheStar(): void
    {
        $contents = "<?php\n  /**\n   * Summary.\n   *\n   *  @param int \$a\n      No star.\n   */\n";
        $file = self::sourceFile($contents);
        $rows = DocblockRows::of($file, self::docblock($contents));

        self::assertSame(['', 'Summary.', '', '@param int $a', 'No star.', ''], self::texts($rows));
        self::assertSame(strpos($contents, needle: '* Summary'), $rows[1]->star);
        self::assertNull($rows[4]->star);
        self::assertNull($rows[5]->star);
        self::assertSame('  ', DocblockRows::indent($file, self::docblock($contents)));
        self::assertSame('   *', DocblockRows::prefix($file, $rows[1]));
        self::assertSame(6, $rows[3]->column());
    }

    public function testReadsTheOpeningLineAndTheClosingLine(): void
    {
        $contents = "<?php\n/** @var int  */\n";
        $rows = DocblockRows::of(self::sourceFile($contents), self::docblock($contents));

        self::assertCount(1, $rows);
        self::assertSame('@var int', $rows[0]->text);
        self::assertSame('@var', $rows[0]->tag());
        self::assertSame('int', $rows[0]->value());

        $contents = "<?php\n/**\n * Summary. */\n";
        $rows = DocblockRows::of(self::sourceFile($contents), self::docblock($contents));

        self::assertSame(['', 'Summary.'], self::texts($rows));
    }

    public function testKeepsTheCarriageReturnOutOfTheText(): void
    {
        $contents = "<?php\r\n/**\r\n * Summary.  \r\n *\r\n */\r\n";
        $rows = DocblockRows::of(self::sourceFile($contents), self::docblock($contents));

        self::assertSame(['', 'Summary.', '', ''], self::texts($rows));
        self::assertSame(strpos($contents, needle: " *\r\n */"), $rows[2]->start);
    }

    public function testTellsTagsFromDirectivesAndProse(): void
    {
        $contents = "<?php\n/**\n * @see  foo()\n * phpcs:ignore Some.Sniff\n * @ not a tag\n * {@inheritdoc}\n */\n";
        $rows = DocblockRows::of(self::sourceFile($contents), self::docblock($contents));

        self::assertSame('@see', $rows[1]->tag());
        self::assertSame('foo()', $rows[1]->value());
        self::assertTrue($rows[2]->isDirective());
        self::assertNull($rows[2]->tag());
        self::assertTrue($rows[3]->isProse());
        self::assertTrue($rows[4]->isProse());
        self::assertSame([1], DocblockRows::tags($rows));
        self::assertSame(1, DocblockRows::nextContent($rows, 0));
        self::assertSame(4, DocblockRows::previousContent($rows, 5));
    }

    /**
     * @param list<DocblockRow> $rows
     * @return list<string>
     */
    private static function texts(array $rows): array
    {
        return array_map(static fn(DocblockRow $row): string => $row->text, $rows);
    }

    private static function docblock(string $contents): Span
    {
        $start = (int) strpos($contents, needle: '/**');
        $end = (int) strpos($contents, needle: '*/', offset: $start + 3) + 2;
        self::assertSame('*/', substr($contents, $end - 2, length: 2));

        return new Span($start, $end);
    }

    /**
     * Each call gets its own path, since `DocblockRows::of()` keeps its
     * result per path and span.
     */
    private static function sourceFile(string $contents): SourceFile
    {
        static $counter = 0;
        ++$counter;

        return new SourceFile(
            PHPVersion::fromParts(8, 1),
            "rows-{$counter}.php",
            $contents,
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore('', 0),
            null,
        );
    }
}
