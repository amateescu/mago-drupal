<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Linter\Rules\FileEncodingRule;
use Mago\Sdk\Syntax\NodeKind;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The corpus cannot hold these cases. A file with invalid UTF-8 has no valid
 * line to write a `@mago-expect` comment on, and the issue is at the first
 * tag.
 */
final class FileEncodingRuleTest extends TestCase
{
    public function testReportsAtTheFirstOpenTagWithoutQuotingBytes(): void
    {
        $issues = ProgramLint::run(new FileEncodingRule(), "<?php\n// caf\xE9\n", [[NodeKind::OpeningTag, 0, 6]]);

        self::assertCount(1, $issues);
        self::assertSame('File encoding is invalid, expected UTF-8.', $issues[0]->message);
        self::assertSame(0, $issues[0]->annotations[0]->span->start);
        self::assertSame(0, $issues[0]->annotations[0]->span->end);
    }

    public function testReportsAtInlineTextBeforeTheFirstTag(): void
    {
        $issues = ProgramLint::run(new FileEncodingRule(), "\n<?php\n// caf\xE9\n", [
            [NodeKind::Inline,     0, 1],
            [NodeKind::OpeningTag, 1, 7],
        ]);

        self::assertSame(0, $issues[0]->annotations[0]->span->start);
    }

    public function testIgnoresTheLineBreakAfterACloseTag(): void
    {
        $issues = ProgramLint::run(new FileEncodingRule(), "<?= 'a' ?>\n<?php\n// caf\xE9\n", [
            [NodeKind::EchoTag,    0,  10],
            [NodeKind::Inline,     10, 11],
            [NodeKind::OpeningTag, 11, 17],
        ]);

        self::assertCount(1, $issues);
        self::assertSame(11, $issues[0]->annotations[0]->span->start);
    }

    public function testSkipsAFileWithOnlyEchoTags(): void
    {
        $issues = ProgramLint::run(new FileEncodingRule(), "<?= 'Caf\xE9' ?>\n", [
            [NodeKind::EchoTag, 0,  13],
            [NodeKind::Inline,  13, 14],
        ]);

        self::assertSame([], $issues);
    }

    public function testSkipsValidUtf8WithAMark(): void
    {
        $issues = ProgramLint::run(new FileEncodingRule(), "\xEF\xBB\xBF<?php\n// caf\xC3\xA9 \xF0\x9F\x98\x80\n", [
            [NodeKind::Inline,     0, 3],
            [NodeKind::OpeningTag, 3, 9],
        ]);

        self::assertSame([], $issues);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid(): array
    {
        return [
            'overlong' => ["\xC0\xAF"],
            'surrogate' => ["\xED\xA0\x80"],
            'cut off' => ["\xE2\x82"],
            'lone 0xFF' => ["\xFF"],
            'UTF-16 mark' => ["\xFF\xFE"],
            'Latin-1' => ["\xE9"],
        ];
    }

    #[DataProvider('invalid')]
    public function testReportsEncodingsThatAreNotUtf8(string $bytes): void
    {
        $issues = ProgramLint::run(new FileEncodingRule(), "<?php\n// {$bytes}\n", [[NodeKind::OpeningTag, 0, 6]]);

        self::assertCount(1, $issues);
    }
}
