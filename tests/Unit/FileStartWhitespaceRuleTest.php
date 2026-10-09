<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Linter\Rules\FileStartWhitespaceRule;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Syntax\NodeKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * The reported shapes also run through `tests/fixes/file-start-whitespace`.
 * These cases cover the shapes that stay quiet, which the corpus cannot
 * express because the issue sits before the first line a pragma could go on.
 */
final class FileStartWhitespaceRuleTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function whitespace(): array
    {
        return [
            'newline' => ["\n"],
            'blank lines and spaces' => ["\n\n  "],
            'tab and CRLF' => ["\t\r\n"],
            'no-break spaces' => ["\xC2\xA0\xC2\xA0"],
            'form feed' => ["\x0C"],
            'vertical tab' => ["\x0B\n"],
            'next line' => ["\xC2\x85"],
            'line separator' => ["\xE2\x80\xA8"],
            'ideographic space' => ["\xE3\x80\x80"],
        ];
    }

    #[DataProvider('whitespace')]
    public function testReportsAtTheTagAndDeletesTheTextAsPotentiallyUnsafe(string $text): void
    {
        $length = strlen($text);
        $issues = ProgramLint::run(new FileStartWhitespaceRule(), $text . "<?php\n", [
            [NodeKind::Inline, 0, $length],
            [NodeKind::OpeningTag, $length, $length + 6],
        ]);

        self::assertCount(1, $issues);
        self::assertSame('Additional whitespace found at start of file.', $issues[0]->message);
        self::assertSame($length, $issues[0]->annotations[0]->span->start);
        self::assertCount(1, $issues[0]->edits);
        self::assertSame(0, $issues[0]->edits[0]->span->start);
        self::assertSame($length, $issues[0]->edits[0]->span->end);
        self::assertSame('', $issues[0]->edits[0]->newText);
        self::assertSame(Safety::PotentiallyUnsafe, $issues[0]->edits[0]->safety);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function notWhitespace(): array
    {
        return [
            'byte order mark' => ["\xEF\xBB\xBF"],
            'shebang' => ["#!/usr/bin/env php\n"],
            'text' => ['text'],
            'zero-width space' => ["\xE2\x80\x8B"],
            'NUL' => ["\0"],
            'whitespace and a Latin-1 byte' => ["\n\xE9"],
            'invalid byte' => [" \xFF\n"],
        ];
    }

    #[DataProvider('notWhitespace')]
    public function testSkipsTextThatIsNotWhitespace(string $text): void
    {
        $length = strlen($text);
        $issues = ProgramLint::run(new FileStartWhitespaceRule(), $text . "<?php\n", [
            [NodeKind::Inline, 0, $length],
            [NodeKind::OpeningTag, $length, $length + 6],
        ]);

        self::assertSame([], $issues);
    }

    public function testSkipsAFileThatStartsWithTheTag(): void
    {
        $issues = ProgramLint::run(new FileStartWhitespaceRule(), "<?php\n", [[NodeKind::OpeningTag, 0, 6]]);

        self::assertSame([], $issues);
    }

    public function testSkipsWhitespaceWithNoTag(): void
    {
        self::assertSame([], ProgramLint::run(new FileStartWhitespaceRule(), "\n\n", [[NodeKind::Inline, 0, 2]]));
    }

    public function testSkipsWhitespaceBeforeAnEchoTag(): void
    {
        $issues = ProgramLint::run(new FileStartWhitespaceRule(), "\n<?= 1 ?>", [
            [NodeKind::Inline,  0, 1],
            [NodeKind::EchoTag, 1, 9],
        ]);

        self::assertSame([], $issues);
    }

    public function testSkipsALaterTagThatFollowsACloseTag(): void
    {
        $issues = ProgramLint::run(new FileStartWhitespaceRule(), "<?php ?>\n<?php\n", [
            [NodeKind::OpeningTag, 0, 6],
            [NodeKind::ClosingTag, 6, 8],
            [NodeKind::Inline,     8, 9],
            [NodeKind::OpeningTag, 9, 15],
        ]);

        self::assertSame([], $issues);
    }
}
