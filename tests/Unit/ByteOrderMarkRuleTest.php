<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Linter\Rules\ByteOrderMarkRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * The corpus cannot hold these cases. An issue at the first byte has no line
 * above it for a `@mago-expect` comment.
 */
final class ByteOrderMarkRuleTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function marks(): array
    {
        return [
            'UTF-8' => ["\xEF\xBB\xBF", 'UTF-8'],
            'UTF-16 BE' => ["\xFE\xFF", 'UTF-16 (BE)'],
            'UTF-16 LE' => ["\xFF\xFE", 'UTF-16 (LE)'],
        ];
    }

    #[DataProvider('marks')]
    public function testReportsEachMarkOnceOverItsBytes(string $mark, string $name): void
    {
        $issues = ProgramLint::run(new ByteOrderMarkRule(), $mark . "<?php\n" . $mark);

        self::assertCount(1, $issues);
        self::assertSame("Remove the {$name} byte order mark at the start of the file.", $issues[0]->message);
        self::assertSame(0, $issues[0]->annotations[0]->span->start);
        self::assertSame(strlen($mark), $issues[0]->annotations[0]->span->end);
        self::assertSame([], $issues[0]->edits);
    }

    public function testReportsAFileThatHoldsOnlyTheMark(): void
    {
        self::assertCount(1, ProgramLint::run(new ByteOrderMarkRule(), "\xEF\xBB\xBF"));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unmarked(): array
    {
        return [
            'in a string' => ["<?php\n\$a = \"\xEF\xBB\xBF\";\n"],
            'after a newline' => ["\n\xEF\xBB\xBF<?php\n"],
            'after spaces' => ["  \xEF\xBB\xBF<?php\n"],
            'partial UTF-8 mark' => ["\xEF\xBB<?php\n"],
            'lone 0xFF' => ["\xFF<?php\n"],
            'no mark' => ["<?php\n"],
            'empty' => [''],
        ];
    }

    #[DataProvider('unmarked')]
    public function testSkipsBytesThatAreNotAMarkAtTheStart(string $contents): void
    {
        self::assertSame([], ProgramLint::run(new ByteOrderMarkRule(), $contents));
    }
}
