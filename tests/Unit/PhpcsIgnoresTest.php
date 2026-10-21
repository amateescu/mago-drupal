<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Ignore\PhpcsIgnores;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use function range;
use function strpos;
use function substr_count;

final class PhpcsIgnoresTest extends TestCase
{
    private const TODO = 'Generic.Commenting.Todo.TaskFound';

    private const CONSTANT = 'Generic.PHP.LowerCaseConstant.Found';

    /**
     * Comments in the shapes of PHP_CodeSniffer's own ErrorSuppressionTest,
     * with the lines that phpcs 3.13 ignores for each of two codes. The lines
     * come from phpcs's `getIgnoredLines()` on the same text.
     *
     * @mago-expect lint:halstead
     *
     * @return array<string, array{string, list<int>, list<int>}>
     */
    public static function phpcsCases(): array
    {
        return [
            'slash comments' => [
                "<?php\n// phpcs:disable\n\$var = FALSE;\n// phpcs:enable\n\$var = FALSE;\n",
                [2, 3, 4],
                [2, 3, 4],
            ],
            'hash comments, with an @ and mixed case' => [
                "<?php\n# @PHPCS:Disable\n\$var = FALSE;\n# pHPcs:enabLE\n\$var = FALSE;\n",
                [2, 3, 4],
                [2, 3, 4],
            ],
            'star comment on several lines' => [
                "<?php\n/*\n * phpcs:disable\n */\n\$var = FALSE;\n/*\n phpcs:enable\n */\n\$var = FALSE;\n",
                [3, 4, 5, 6, 7],
                [3, 4, 5, 6, 7],
            ],
            'docblock on one line' => [
                "<?php\n/** phpcs:disable */\n\$var = FALSE;\n/** phpcs:enable */\n\$var = FALSE;\n",
                [2, 3],
                [2, 3],
            ],
            'old start and end' => [
                "<?php\n// @codingStandardsIgnoreStart\n\$var = FALSE;\n// @codingStandardsIgnoreEnd\n\$var = FALSE;\n",
                [2, 3, 4],
                [2, 3, 4],
            ],
            'ignore on its own line and after code' => [
                "<?php\n// phpcs:ignore\n\$var = FALSE;\n\$var = FALSE; // phpcs:ignore\n\$var = FALSE;\n",
                [2, 3, 4],
                [2, 3, 4],
            ],
            'ignore above a blank line' => [
                "<?php\n// phpcs:ignore Generic.PHP.LowerCaseConstant\n\n\$var = FALSE;\n",
                [2],
                [2, 3],
            ],
            'ignore code, sniff, category and standard' => [
                "<?php\n"
                    . "// phpcs:ignore Generic.PHP.LowerCaseConstant.Found\n\$var = FALSE; //TODO: one\n"
                    . "// phpcs:ignore Generic.Commenting.Todo\n\$var = FALSE; //TODO: two\n"
                    . "# phpcs:ignore Generic.Commenting\n\$var = FALSE; //TODO: three\n"
                    . "// phpcs:ignore Generic\n\$var = FALSE; //TODO: four\n",
                [2, 4, 5, 6, 7, 8, 9],
                [2, 3, 4, 6, 8, 9],
            ],
            'ignore several codes, with a note' => [
                "<?php\n"
                    . "// phpcs:ignore Generic.Commenting.Todo, Generic.PHP.LowerCaseConstant -- Because reasons\n"
                    . "\$var = FALSE; //TODO: write some code\n\$var = FALSE; //TODO: write some code\n",
                [2, 3],
                [2, 3],
            ],
            'ignore inside a region' => [
                "<?php\n// phpcs:disable Generic.Commenting.Todo\n// phpcs:ignore Generic.PHP.LowerCaseConstant\n"
                    . "\$var = FALSE; //TODO: write some code\n\$var = FALSE; //TODO: write some code\n",
                [2, 3, 4, 5],
                [2, 3, 4],
            ],
            'enable a sniff of a disabled standard' => [
                "<?php\n// phpcs:disable Generic\n\$var = FALSE;\n//TODO: write some code\n"
                    . "// phpcs:enable Generic.Commenting.Todo\n//TODO: write some code\n\$var = FALSE;\n"
                    . "// phpcs:disable Generic.Commenting.Todo\n//TODO: write some code\n// phpcs:enable\n"
                    . "//TODO: write some code\n",
                [2, 3, 4, 5, 8, 9, 10],
                [2, 3, 4, 5, 6, 7, 8, 9, 10],
            ],
            'enable the standard of a disabled category' => [
                "<?php\n# phpcs:disable Generic.Commenting\n\$var = FALSE;\n//TODO: write some code\n"
                    . "# phpcs:enable Generic\n//TODO: write some code\n",
                [2, 3, 4, 5],
                [2, 5],
            ],
            'enable one of two disabled sniffs' => [
                "<?php\n// phpcs:disable Generic.Commenting.Todo,Generic.PHP.LowerCaseConstant\n\$var = FALSE;\n"
                    . "//TODO: write some code\n// phpcs:enable Generic.Commenting.Todo\n//TODO: write some code\n"
                    . "\$var = FALSE;\n",
                [2, 3, 4, 5],
                [2, 3, 4, 5, 6, 7],
            ],
            'enable without a region' => [
                "<?php\n// phpcs:enable Generic.PHP.LowerCaseConstant -- Because reasons\n\$var = FALSE;\n",
                [],
                [],
            ],
            'docblock lines' => [
                "<?php\n/**\n * Text.\n *\n * phpcs:ignore Generic.Commenting.Todo\n */\nfunction a() {}\n"
                    . "/**\n * @phpcs:ignore\n * @codingStandardsIgnoreLine\n */\n\$var = FALSE;\n",
                [5, 6, 9, 10],
                [5, 9, 10],
            ],
            'old line form, on its own line and after code' => [
                "<?php\n// @codingStandardsIgnoreLine\n\$var = FALSE;\n\$var = FALSE; // @codingStandardsIgnoreLine\n"
                    . "\$var = FALSE;\n",
                [2, 3, 4],
                [2, 3, 4],
            ],
            'set on its own line' => [
                "<?php\n// phpcs:set Generic.PHP.LowerCaseConstant foo bar\n\$var = FALSE;\n",
                [2],
                [2],
            ],
            'Windows line endings' => [
                "<?php\r\n// phpcs:ignore\r\n\$var = FALSE;\r\n\$var = FALSE;\r\n",
                [2, 3],
                [2, 3],
            ],
        ];
    }

    /**
     * @param list<int> $todo
     * @param list<int> $constant
     */
    #[DataProvider('phpcsCases')]
    public function testCoversTheLinesThatPhpcsCovers(string $code, array $todo, array $constant): void
    {
        self::assertSame($todo, self::covered($code, self::TODO));
        self::assertSame($constant, self::covered($code, self::CONSTANT));
    }

    /**
     * `phpcs:ignoreFile` and `@codingStandardsIgnoreFile` cover every line,
     * wherever they are in the file, a docblock included.
     */
    public function testIgnoreFileCoversEveryLine(): void
    {
        foreach ([
            "<?php\n\$var = FALSE;\n/**\n * phpcs:ignoreFile -- Generated.\n */\n\$var = FALSE;\n",
            "<?php\n\$var = FALSE;\n// @codingStandardsIgnoreFile\n",
        ] as $code) {
            self::assertSame(range(1, substr_count($code, needle: "\n") + 1), self::covered($code, self::CONSTANT));
        }
    }

    /**
     * Only a comment without codes, or `phpcs:ignoreFile`, covers a line for
     * a rule that doesn't port any Coder code.
     */
    public function testAnEmptyCodeListNeedsACommentWithoutCodes(): void
    {
        $code = "<?php\n// phpcs:ignore Generic\n\$var = FALSE;\n// phpcs:ignore\n\$var = FALSE;\n";
        $ignores = PhpcsIgnores::of($code);

        self::assertNotNull($ignores);
        self::assertFalse($ignores->ignores(3, []));
        self::assertTrue($ignores->ignores(5, []));
    }

    /**
     * One of several codes is enough, as for a rule that ports more than one.
     */
    public function testOneOfSeveralCodesIsEnough(): void
    {
        $code = "<?php\n// phpcs:ignore Generic.Commenting.Todo\n\$var = FALSE;\n";
        $ignores = PhpcsIgnores::of($code);

        self::assertNotNull($ignores);
        self::assertTrue($ignores->ignores(3, [self::CONSTANT, self::TODO]));
        self::assertFalse($ignores->ignores(3, [self::CONSTANT]));
    }

    /**
     * The line of an offset counts the file's line ending, as phpcs does.
     */
    public function testLineOfAnOffset(): void
    {
        $code = "<?php\r\n// phpcs:ignore\r\n\$var = FALSE;\r\n";
        $ignores = PhpcsIgnores::of($code);

        self::assertNotNull($ignores);
        self::assertSame(1, $ignores->line($code, 0));
        self::assertSame(3, $ignores->line($code, (int) strpos($code, needle: '$var')));
    }

    /**
     * A file without suppression comments, or with a code name in a string
     * only, doesn't cover anything.
     */
    public function testFileWithoutCommentsIsNull(): void
    {
        self::assertNull(PhpcsIgnores::of("<?php\n\$var = FALSE;\n"));
        self::assertNull(PhpcsIgnores::of("<?php\n\$text = 'phpcs:ignore';\n"));
    }

    /**
     * The lines from 1 to the last that the comments cover for the code.
     *
     * @return list<int>
     */
    private static function covered(string $code, string $coderCode): array
    {
        $ignores = PhpcsIgnores::of($code);
        $lines = [];
        for ($line = 1; $line <= (substr_count($code, needle: "\n") + 1); $line++) {
            if ($ignores?->ignores($line, [$coderCode]) !== true) {
                continue;
            }

            $lines[] = $line;
        }

        return $lines;
    }
}
