<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Linter\Rules\DocCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentRule;
use Closure;
use Mago\Sdk\CancellationTokenInterface;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;

use function array_map;
use function pack;
use function strlen;
use function strpos;

/**
 * Core's `phpcs.xml.dist` turns off three comment checks, which the rules
 * leave out when the worker runs with `--core`.
 */
final class CoreExclusionsTest extends TestCase
{
    private const CONTENTS = <<<'PHP'
        <?php

        /**
         * Summary.
         *
         * The long description ends with a letter
         */
        function corpus_core_exclusions(): int
        {
            // Counts nothing and ends with a letter

            return 0;
        }

        PHP;

    public function testReportsTheThreeChecksOutsideCore(): void
    {
        self::assertSame(
            [
                'End an inline comment with a full stop, an exclamation mark, a question mark or a colon.',
                'Remove the blank line below the comment.',
            ],
            self::messages(new InlineCommentRule()),
        );
        self::assertSame(
            ['The long description must end with terminal punctuation.'],
            self::messages(new DocCommentRule()),
        );
    }

    public function testLeavesTheThreeChecksOutOnCore(): void
    {
        self::assertSame([], self::messages(new InlineCommentRule(core: true)));
        self::assertSame([], self::messages(new DocCommentRule(core: true)));
    }

    /**
     * @return list<string>
     */
    private static function messages(Rule $rule): array
    {
        static $counter = 0;
        ++$counter;

        $contents = self::CONTENTS;
        $records = '';
        foreach ([[4, '/**'], [1, '// Counts']] as [$kind, $needle]) {
            $start = (int) strpos($contents, $needle);
            $end = $kind === 4
                ? (int) strpos($contents, needle: '*/', offset: $start) + 2
                : (int) strpos($contents, needle: "\n", offset: $start);
            $records .= pack('CNN', $kind, $start, $end);
        }

        $file = new SourceFile(
            PHPVersion::fromParts(8, 1),
            "core-exclusions-{$counter}.php",
            $contents,
            [],
            new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore($records, 2),
            null,
        );
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
        $context = new LintContext($file, new Node(0, NodeKind::Program, new Span(0, strlen($contents)), null), $token);
        $rule->lint($context);

        return array_map(static fn($issue): string => $issue->message, $context->issues);
    }
}
