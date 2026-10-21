<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Comment\CommentAttachment;
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

use function count;
use function pack;
use function strlen;
use function strpos;

final class CommentAttachmentTest extends TestCase
{
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
            'test.php',
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
     * node doesn't have a store behind it.
     */
    private static function declarationAt(string $contents, string $needle): Node
    {
        return new Node(0, NodeKind::Function, self::span($contents, $needle), null);
    }

    public function testClosestReturnsTheDocblockThroughDirectives(): void
    {
        $contents = "<?php\n\n/** Doc. */\n// @mago-expect lint:x\n// phpcs:ignore Some.Sniff\nfunction foo() {}\n";
        $file = self::sourceFileWithTrivia($contents, [
            [TriviaKind::DocBlockComment,   '/** Doc. */'],
            [TriviaKind::SingleLineComment, '// @mago-expect lint:x'],
            [TriviaKind::SingleLineComment, '// phpcs:ignore Some.Sniff'],
        ]);

        $closest = CommentAttachment::closest($file, self::declarationAt($contents, 'function foo() {}'));

        self::assertNotNull($closest);
        self::assertSame(TriviaKind::DocBlockComment, $closest->kind);
    }

    public function testClosestReturnsNullWhenCodeSitsBetween(): void
    {
        $contents = "<?php\n\n/** Doc. */\n\$noise = 1;\nfunction foo() {}\n";
        $file = self::sourceFileWithTrivia($contents, [[TriviaKind::DocBlockComment, '/** Doc. */']]);

        self::assertNull(CommentAttachment::closest($file, self::declarationAt($contents, 'function foo() {}')));
    }

    public function testClosestReturnsNullWhenOnlyDirectivesPrecede(): void
    {
        $contents = "<?php\n\n// @mago-expect lint:x\nfunction foo() {}\n";
        $file = self::sourceFileWithTrivia($contents, [[TriviaKind::SingleLineComment, '// @mago-expect lint:x']]);

        self::assertNull(CommentAttachment::closest($file, self::declarationAt($contents, 'function foo() {}')));
    }

    /**
     * The lower edge of the boundary search. Every trivia entry ends after
     * the declaration starts, so there aren't any candidates.
     */
    public function testClosestReturnsNullWhenAllTriviaFollowTheDeclaration(): void
    {
        $contents = "<?php\n\nfunction foo() {}\n// A trailing comment.\n";
        $file = self::sourceFileWithTrivia($contents, [[TriviaKind::SingleLineComment, '// A trailing comment.']]);

        self::assertNull(CommentAttachment::closest($file, self::declarationAt($contents, 'function foo() {}')));
    }

    public function testClosestReturnsAPlainCommentWithItsKind(): void
    {
        $contents = "<?php\n\n/* Plain comment. */\nfunction foo() {}\n";
        $file = self::sourceFileWithTrivia($contents, [[TriviaKind::MultiLineComment, '/* Plain comment. */']]);

        $closest = CommentAttachment::closest($file, self::declarationAt($contents, 'function foo() {}'));

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

        self::assertTrue(CommentAttachment::isDirective($file, $trivia[0]));
        self::assertTrue(CommentAttachment::isDirective($file, $trivia[1]));
        self::assertTrue(CommentAttachment::isDirective($file, $trivia[2]));
        self::assertFalse(CommentAttachment::isDirective($file, $trivia[3]));
        self::assertFalse(CommentAttachment::isDirective($file, $trivia[4]));
    }

    public function testFindsACommentBetweenTwoOffsets(): void
    {
        $contents = "<?php\n#[A('// x')] f('# y', /* z */ 1); // w ?>\n<?php g(); # v\n";
        $file = self::sourceFileWithTrivia($contents, [
            [TriviaKind::MultiLineComment,  '/* z */'],
            [TriviaKind::SingleLineComment, '// w '],
            [TriviaKind::HashComment,       '# v'],
        ]);
        $code = self::span($contents, "#[A('// x')] f('# y', ");
        $block = self::span($contents, '/* z */');
        $close = self::span($contents, "?>\n<?php g(); ");

        self::assertFalse(CommentAttachment::hasCommentBetween($file, $code->start, $code->end));
        self::assertTrue(CommentAttachment::hasCommentBetween($file, $code->start, $block->start + 1));
        self::assertTrue(CommentAttachment::hasCommentBetween($file, $block->end - 1, $block->end + 2));
        self::assertFalse(CommentAttachment::hasCommentBetween($file, $block->end, $block->end + 4));
        self::assertFalse(CommentAttachment::hasCommentBetween($file, $close->start, $close->end));
        self::assertTrue(CommentAttachment::hasCommentBetween($file, $close->start, $close->end + 1));
        self::assertFalse(CommentAttachment::hasCommentBetween($file, $block->start, $block->start));
    }
}
