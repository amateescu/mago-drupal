<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Linter\Rules\FileCommentRule;
use Closure;
use Mago\Sdk\CancellationTokenInterface;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;
use PHPUnit\Framework\TestCase;

use function pack;
use function strlen;
use function strpos;

/**
 * Every check this rule makes is anchored to "the first trivia in the file",
 * so a suppressing `@mago-expect` comment would itself become that trivia
 * and change the answer. The corpus can only prove the happy path does not
 * false-positive; the negative cases are exercised here instead.
 */
final class FileCommentRuleTest extends TestCase
{
    /**
     * Finds $needle's span in $contents, so a fixture never hand-counts
     * byte offsets.
     */
    private static function spanOf(string $contents, string $needle): Span
    {
        $start = strpos($contents, $needle);
        self::assertNotFalse($start, "'{$needle}' not found in the fixture.");

        return new Span($start, $start + strlen($needle));
    }

    /**
     * `Docblocks::tags()` memoizes per (path, span), and several fixtures
     * below share the literal path `node.module`, so each call gets its own
     * unique path instead: without it, one test's cached result could leak
     * into another's assertions if two fixtures ever produced the same
     * docblock span. The extension stays intact, since `FileCommentRule`
     * reads it to decide whether the file is procedural at all.
     *
     * @return list<Issue>
     */
    private static function lint(string $path, string $contents, ?TriviaKind $trivia = null, ?Span $span = null): array
    {
        static $counter = 0;
        ++$counter;

        $records = '';
        if ($trivia !== null && $span !== null) {
            $byte = match ($trivia) {
                TriviaKind::SingleLineComment => 1,
                TriviaKind::MultiLineComment => 2,
                TriviaKind::HashComment => 3,
                TriviaKind::DocBlockComment => 4,
            };

            $records = pack('CNN', $byte, $span->start, $span->end);
        }

        $file = new SourceFile(
            PHPVersion::fromParts(8, 1),
            "test-{$counter}-{$path}",
            $contents,
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore($records, $records === '' ? 0 : 1),
            null,
        );

        $node = new Node(0, NodeKind::Program, new Span(0, strlen($contents)), null);
        $token = new class implements CancellationTokenInterface {
            public function isCancelled(): bool
            {
                return false;
            }

            public function throwIfCancelled(): void {}

            public function subscribe(Closure $callback): int
            {
                return 0;
            }

            public function unsubscribe(int $subscription): void {}
        };

        $context = new LintContext($file, $node, $token);
        (new FileCommentRule())->lint($context);

        return $context->issues;
    }

    public function testReportsNothingOnAFineFileComment(): void
    {
        $contents = "<?php\n\n/**\n * @file\n * Does something.\n */\n";

        $issues = self::lint(
            'node.module',
            $contents,
            TriviaKind::DocBlockComment,
            self::spanOf($contents, "/**\n * @file\n * Does something.\n */"),
        );

        self::assertSame([], $issues);
    }

    public function testReportsNothingOnANonProceduralFile(): void
    {
        self::assertSame([], self::lint('Node.php', "<?php\n\nclass Node {}\n"));
    }

    public function testReportsMissingWhenNothingPrecedesTheCode(): void
    {
        $issues = self::lint('node.module', "<?php\n\nfunction foo(): void {}\n");

        self::assertCount(1, $issues);
        self::assertStringContainsString('Missing file doc comment', $issues[0]->message);
    }

    public function testReportsWrongStyleForAPlainComment(): void
    {
        $contents = "<?php\n\n/* Not a docblock. */\n";

        $issues = self::lint(
            'node.module',
            $contents,
            TriviaKind::MultiLineComment,
            self::spanOf($contents, '/* Not a docblock. */'),
        );

        self::assertCount(1, $issues);
        self::assertStringContainsString('must use "/**" style comments', $issues[0]->message);
    }

    public function testReportsMissingFileTagOnADocblockWithoutOne(): void
    {
        $contents = "<?php\n\n/**\n * Not tagged as a file comment.\n */\n";

        $issues = self::lint(
            'node.module',
            $contents,
            TriviaKind::DocBlockComment,
            self::spanOf($contents, "/**\n * Not tagged as a file comment.\n */"),
        );

        self::assertCount(1, $issues);
        self::assertStringContainsString('must have an @file tag', $issues[0]->message);
    }

    public function testReportsMissingWhenTheCommentIsNotAtTheFileStart(): void
    {
        $contents = "<?php\n\ndeclare(strict_types=1);\n\n/**\n * @file\n */\n";

        $issues = self::lint(
            'node.module',
            $contents,
            TriviaKind::DocBlockComment,
            self::spanOf($contents, "/**\n * @file\n */"),
        );

        self::assertCount(1, $issues);
        self::assertStringContainsString('Missing file doc comment', $issues[0]->message);
    }
}
