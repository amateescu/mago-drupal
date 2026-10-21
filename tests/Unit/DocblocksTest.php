<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;

use function array_map;
use function strlen;
use function strpos;
use function substr;

/**
 * @mago-expect lint:too-many-methods
 */
final class DocblocksTest extends TestCase
{
    /**
     * `lines()` and `tags()` call only `SourceFile::getText()`. That method
     * reads directly from `$contents`, so this test never touches the
     * node, name and trivia stores behind a real snapshot.
     */
    private static function sourceFile(string $contents): SourceFile
    {
        return new SourceFile(
            PHPVersion::fromParts(8, 1),
            'test.php',
            $contents,
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore('', 0),
            null,
        );
    }

    private static function span(string $contents, string $needle): Span
    {
        $start = strpos($contents, $needle);
        self::assertNotFalse($start, "'{$needle}' not found in the fixture.");

        return new Span($start, $start + strlen($needle));
    }

    public function testLinesStripMarkersAndKeepAbsoluteOffsets(): void
    {
        $contents = "<?php\n\n/**\n * Summary.\n *\n * @param string \$a\n */\n";
        $docblock = self::span($contents, "/**\n * Summary.\n *\n * @param string \$a\n */");
        $file = self::sourceFile($contents);

        $lines = Docblocks::lines($file, $docblock);

        self::assertSame(
            ['', 'Summary.', '', '@param string $a', ''],
            array_map(static fn($line) => $line->text, $lines),
        );

        // The third line is "Summary." without the "/**\n * " prefix, so its
        // offset is exactly at the "S".
        self::assertSame('S', substr($contents, $lines[1]->offset, length: 1));
    }

    public function testLinesHandleASingleLineDocblock(): void
    {
        $contents = '/** Summary. */';
        $file = self::sourceFile($contents);

        $lines = Docblocks::lines($file, new Span(0, strlen($contents)));

        self::assertSame(['Summary.'], array_map(static fn($line) => $line->text, $lines));
        self::assertSame(4, $lines[0]->offset);
    }

    public function testLeadingLinesStopAtTheFirstTag(): void
    {
        $contents = "/**\n * Summary.\n *\n * Long description.\n *\n * @param string \$a\n */";
        $file = self::sourceFile($contents);

        $leading = Docblocks::leadingLines($file, new Span(0, strlen($contents)));

        // The opening "/**" line is blank content. The result keeps it in the
        // same way as any other blank line.
        self::assertSame(
            ['', 'Summary.', '', 'Long description.', ''],
            array_map(static fn($line) => $line->text, $leading),
        );
    }

    public function testTagsGroupContinuationLinesUntilTheNextTag(): void
    {
        $contents = "/**\n * @param string \$a\n *   A multi-line\n *   description.\n * @return bool\n */";
        $file = self::sourceFile($contents);

        $tags = Docblocks::tags($file, new Span(0, strlen($contents)));

        self::assertCount(2, $tags);
        self::assertSame('param', $tags[0]->name);
        self::assertSame('string $a   A multi-line   description.', $tags[0]->content());
        self::assertSame('return', $tags[1]->name);
        self::assertSame('bool', $tags[1]->content());
    }

    public function testTagsTreatCodeMarkersAsTagsOfTheirOwn(): void
    {
        $contents = "/**\n * @param array \$a\n *   Keyed by name. For example:\n * @code\n *   ['x' => 1]\n * @endcode\n * @return bool\n */";
        $file = self::sourceFile($contents);

        $tags = Docblocks::tags($file, new Span(0, strlen($contents)));

        // Coder's tokenizer does the same, so `drupal/doc-tag-groups` can
        // see an example that splits two @param groups.
        self::assertSame(
            ['param', 'code', 'endcode', 'return'],
            array_map(static fn(DocblockTag $tag): string => $tag->name, $tags),
        );
    }

    public function testTagNameSpanCoversOnlyTheAtNameToken(): void
    {
        $contents = "/**\n * @return bool\n */";
        $file = self::sourceFile($contents);

        $tag = Docblocks::tags($file, new Span(0, strlen($contents)))[0];

        self::assertSame('@return', substr($contents, $tag->nameSpan->start, $tag->nameSpan->length()));
    }

    public function testTagsReturnsNothingWithoutAnyTag(): void
    {
        $contents = "/**\n * Just a summary, no tags.\n */";
        $file = self::sourceFile($contents);

        self::assertSame([], Docblocks::tags($file, new Span(0, strlen($contents))));
    }

    public function testLinesAndParagraphsReadANewFileWithTheSamePath(): void
    {
        $first = "/**\n * First.\n */";
        $second = "/**\n * Again.\n */";
        $span = new Span(0, strlen($first));

        Docblocks::lines(self::sourceFile($first), $span);
        Docblocks::paragraphs(self::sourceFile($first), $span);
        $file = self::sourceFile($second);

        self::assertSame('Again.', Docblocks::lines($file, $span)[1]->text);
        self::assertSame('Again.', Docblocks::paragraphs($file, $span)[0][0]->text);
    }

    public function testParagraphsSplitsSummaryFromLongDescription(): void
    {
        $contents = "/**\n * Summary.\n *\n * Long description.\n */";
        $file = self::sourceFile($contents);

        [$summary, $description] = Docblocks::paragraphs($file, new Span(0, strlen($contents)));

        self::assertSame(['Summary.'], array_map(static fn($line) => $line->text, $summary));
        self::assertSame(['Long description.'], array_map(static fn($line) => $line->text, $description));
    }

    public function testLinesTakeTheWholeRunOfStarsAndSlashesAsTheCloser(): void
    {
        $contents = "/**\n * Summary. **/";
        $lines = Docblocks::lines(self::sourceFile($contents), new Span(0, strlen($contents)));

        self::assertSame(['', 'Summary.'], array_map(static fn($line) => $line->text, $lines));

        $contents = "/**\n * Summary.\n ***/";
        $lines = Docblocks::lines(self::sourceFile($contents), new Span(0, strlen($contents)));

        self::assertSame(['', 'Summary.', ''], array_map(static fn($line) => $line->text, $lines));
    }

    public function testLinesTakeTheWholeRunOfStarsAsTheOpener(): void
    {
        $contents = "/*******\n * Summary.\n */";
        $lines = Docblocks::lines(self::sourceFile($contents), new Span(0, strlen($contents)));

        self::assertSame(['', 'Summary.', ''], array_map(static fn($line) => $line->text, $lines));

        $contents = '/*** Summary. */';
        $lines = Docblocks::lines(self::sourceFile($contents), new Span(0, strlen($contents)));

        self::assertSame(['Summary.'], array_map(static fn($line) => $line->text, $lines));
    }

    public function testParagraphsReadAFileDocblockAfterTheFileTag(): void
    {
        $contents = "/**\n * @file\n * Summary.\n *\n * Long description.\n *\n * @see foo()\n */";
        $file = self::sourceFile($contents);

        [$summary, $description] = Docblocks::paragraphs($file, new Span(0, strlen($contents)));

        self::assertSame(['Summary.'], array_map(static fn($line) => $line->text, $summary));
        self::assertSame(['Long description.'], array_map(static fn($line) => $line->text, $description));
    }

    public function testParagraphsTakeTheTextOnTheFileTagLineAsTheSummary(): void
    {
        $contents = "/**\n * @file Summary.\n * More.\n */";
        $file = self::sourceFile($contents);

        [$summary, $description] = Docblocks::paragraphs($file, new Span(0, strlen($contents)));

        self::assertSame(['Summary.', 'More.'], array_map(static fn($line) => $line->text, $summary));
        self::assertSame([], $description);
        self::assertSame(strpos($contents, needle: 'Summary.'), $summary[0]->offset);
    }

    public function testParagraphsOfAFileTagWithNothingAfterItAreEmpty(): void
    {
        $contents = "/**\n * @file\n */";

        [$summary, $description] = Docblocks::paragraphs(self::sourceFile($contents), new Span(0, strlen($contents)));

        self::assertSame([], $summary);
        self::assertSame([], $description);
    }

    public function testParagraphsReturnsAnEmptyDescriptionWithoutOne(): void
    {
        $contents = "/**\n * Summary only.\n */";
        $file = self::sourceFile($contents);

        [$summary, $description] = Docblocks::paragraphs($file, new Span(0, strlen($contents)));

        self::assertSame(['Summary only.'], array_map(static fn($line) => $line->text, $summary));
        self::assertSame([], $description);
    }

    /**
     * Regression test. The closing marker must come off a line before the
     * leading star does. Without that, a last line with only "* /" loses its
     * star to the leading strip, and a stray "/" stays as the line's text.
     */
    public function testLinesStripAClosingLineThatIsOnlyTheStarAndMarker(): void
    {
        $contents = "/**\n * Summary.\n *\n * @param int \$a\n */";
        $file = self::sourceFile($contents);

        $lines = Docblocks::lines($file, new Span(0, strlen($contents)));

        self::assertSame(['', 'Summary.', '', '@param int $a', ''], array_map(static fn($line) => $line->text, $lines));
    }
}
