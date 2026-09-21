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
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;
use PHPUnit\Framework\TestCase;

use function array_map;
use function count;
use function pack;
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
     *
     * Each call gets its own path. `Docblocks::lines()` and `tags()` memoize
     * per path and span. Two fixtures in this file can share a span, for
     * example `0..strlen($contents)`, but mean different things. With a
     * shared path, the cached result of one test could leak into the
     * assertions of another test.
     */
    private static function sourceFile(string $contents): SourceFile
    {
        static $counter = 0;
        ++$counter;

        return new SourceFile(
            PHPVersion::fromParts(8, 1),
            "test-{$counter}.php",
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

    /**
     * Builds a SourceFile whose trivia store holds real records. The
     * `closest()` and `isDirective()` tests read trivia and not raw docblock
     * text. This method finds each comment substring in $contents and packs
     * it the way that the wire protocol encodes it.
     *
     * @param list<array{TriviaKind, string}> $comments
     */
    private static function sourceFileWithTrivia(string $contents, array $comments): SourceFile
    {
        static $counter = 0;
        ++$counter;

        $records = '';
        foreach ($comments as [$kind, $needle]) {
            $commentSpan = self::span($contents, $needle);
            $byte = match ($kind) {
                TriviaKind::SingleLineComment => 1,
                TriviaKind::MultiLineComment => 2,
                TriviaKind::HashComment => 3,
                TriviaKind::DocBlockComment => 4,
            };

            $records .= pack('CNN', $byte, $commentSpan->start, $commentSpan->end);
        }

        /** @var int<0, 4294967295> $triviaCount */
        $triviaCount = count($comments);

        return new SourceFile(
            PHPVersion::fromParts(8, 1),
            "trivia-{$counter}.php",
            $contents,
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore($records, $triviaCount),
            null,
        );
    }

    /**
     * Returns a bare declaration node that spans $needle inside $contents.
     * `closest()` reads only the declaration's start offset, so the
     * node has no store behind it.
     */
    private static function declarationAt(string $contents, string $needle): Node
    {
        return new Node(0, NodeKind::Function, self::span($contents, $needle), null);
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

        // Coder's tokenizer does the same, so `drupal/doc-comment` can see
        // an example that splits two @param groups.
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

    public function testSplitTypeSeparatesTheLeadingTypeFromTheRest(): void
    {
        self::assertSame(['string', '$foo The description.'], Docblocks::splitType('string $foo The description.'));
        self::assertSame(['bool', ''], Docblocks::splitType('bool'));
        self::assertSame([null, ''], Docblocks::splitType(''));
    }

    public function testParagraphsSplitsSummaryFromLongDescription(): void
    {
        $contents = "/**\n * Summary.\n *\n * Long description.\n */";
        $file = self::sourceFile($contents);

        [$summary, $description] = Docblocks::paragraphs($file, new Span(0, strlen($contents)));

        self::assertSame(['Summary.'], array_map(static fn($line) => $line->text, $summary));
        self::assertSame(['Long description.'], array_map(static fn($line) => $line->text, $description));
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

    public function testClosestReturnsTheDocblockThroughDirectives(): void
    {
        $contents = "<?php\n\n/** Doc. */\n// @mago-expect lint:x\n// phpcs:ignore Some.Sniff\nfunction foo() {}\n";
        $file = self::sourceFileWithTrivia($contents, [
            [TriviaKind::DocBlockComment,   '/** Doc. */'],
            [TriviaKind::SingleLineComment, '// @mago-expect lint:x'],
            [TriviaKind::SingleLineComment, '// phpcs:ignore Some.Sniff'],
        ]);

        $closest = Docblocks::closest($file, self::declarationAt($contents, 'function foo() {}'));

        self::assertNotNull($closest);
        self::assertSame(TriviaKind::DocBlockComment, $closest->kind);
    }

    public function testClosestReturnsNullWhenCodeSitsBetween(): void
    {
        $contents = "<?php\n\n/** Doc. */\n\$noise = 1;\nfunction foo() {}\n";
        $file = self::sourceFileWithTrivia($contents, [[TriviaKind::DocBlockComment, '/** Doc. */']]);

        self::assertNull(Docblocks::closest($file, self::declarationAt($contents, 'function foo() {}')));
    }

    public function testClosestReturnsNullWhenOnlyDirectivesPrecede(): void
    {
        $contents = "<?php\n\n// @mago-expect lint:x\nfunction foo() {}\n";
        $file = self::sourceFileWithTrivia($contents, [[TriviaKind::SingleLineComment, '// @mago-expect lint:x']]);

        self::assertNull(Docblocks::closest($file, self::declarationAt($contents, 'function foo() {}')));
    }

    /**
     * The lower edge of the boundary search. Every trivia entry ends after
     * the declaration starts, so no candidate exists.
     */
    public function testClosestReturnsNullWhenAllTriviaFollowTheDeclaration(): void
    {
        $contents = "<?php\n\nfunction foo() {}\n// A trailing comment.\n";
        $file = self::sourceFileWithTrivia($contents, [[TriviaKind::SingleLineComment, '// A trailing comment.']]);

        self::assertNull(Docblocks::closest($file, self::declarationAt($contents, 'function foo() {}')));
    }

    public function testClosestReturnsAPlainCommentWithItsKind(): void
    {
        $contents = "<?php\n\n/* Plain comment. */\nfunction foo() {}\n";
        $file = self::sourceFileWithTrivia($contents, [[TriviaKind::MultiLineComment, '/* Plain comment. */']]);

        $closest = Docblocks::closest($file, self::declarationAt($contents, 'function foo() {}'));

        self::assertNotNull($closest);
        self::assertSame(TriviaKind::MultiLineComment, $closest->kind);
    }

    public function testIsDirectiveRecognizesPragmasButNeverDocblocks(): void
    {
        $contents = "<?php\n// @mago-expect lint:x\n// phpcs:ignore Foo\n# @psalm-suppress All\n// Regular prose.\n/** @mago-expect lint:x */\n";
        $file = self::sourceFileWithTrivia($contents, [
            [TriviaKind::SingleLineComment, '// @mago-expect lint:x'],
            [TriviaKind::SingleLineComment, '// phpcs:ignore Foo'],
            [TriviaKind::HashComment,       '# @psalm-suppress All'],
            [TriviaKind::SingleLineComment, '// Regular prose.'],
            [TriviaKind::DocBlockComment,   '/** @mago-expect lint:x */'],
        ]);

        $trivia = $file->getTrivia();

        self::assertTrue(Docblocks::isDirective($file, $trivia[0]));
        self::assertTrue(Docblocks::isDirective($file, $trivia[1]));
        self::assertTrue(Docblocks::isDirective($file, $trivia[2]));
        self::assertFalse(Docblocks::isDirective($file, $trivia[3]));
        self::assertFalse(Docblocks::isDirective($file, $trivia[4]));
    }
}
