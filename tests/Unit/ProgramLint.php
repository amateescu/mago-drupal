<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use Closure;
use Mago\Sdk\CancellationTokenInterface;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_search;
use function count;
use function pack;
use function strlen;

/**
 * Runs a rule that targets Program on a source text and a hand-written tree.
 *
 * A tree is a list of statements. Each statement is the kind of the node it
 * wraps, with that node's start and end offset, in the shape `mago ast`
 * prints for the file.
 */
final class ProgramLint
{
    private const NO_NODE = 4_294_967_295;

    private const KINDS = [
        NodeKind::Program,
        NodeKind::Statement,
        NodeKind::Inline,
        NodeKind::OpeningTag,
        NodeKind::EchoTag,
        NodeKind::ClosingTag,
    ];

    /**
     * Lints $contents with the rule. The Program node is node 0, the
     * statements follow, and each statement is followed by the node it wraps.
     *
     * @param list<array{NodeKind, int, int}> $statements
     *
     * @return list<Issue>
     */
    public static function run(Rule $rule, string $contents, array $statements = []): array
    {
        $first = $statements === [] ? self::NO_NODE : 1;
        $records = self::record(NodeKind::Program, [0, strlen($contents)], [self::NO_NODE, $first, self::NO_NODE]);
        foreach ($statements as $position => [$kind, $start, $end]) {
            $id = 1 + ($position * 2);
            $isLast = $position === (count($statements) - 1);
            $records .= self::record(
                NodeKind::Statement,
                [$start, $end],
                [0, $id + 1, $isLast ? self::NO_NODE : $id + 2],
            );
            $records .= self::record($kind, [$start, $end], [$id, self::NO_NODE, self::NO_NODE]);
        }

        /** @var int<0, 4294967295> $nodeCount */
        $nodeCount = 1 + (count($statements) * 2);
        $file = new SourceFile(
            PHPVersion::fromParts(8, 1),
            'test.module',
            $contents,
            [0],
            new NodeStore(self::KINDS, $records, $nodeCount),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore('', 0),
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

        $context = new LintContext($file, $file->getNode(0), $token);
        $rule->lint($context);

        return $context->issues;
    }

    /**
     * Packs one row of the node table. The offsets are the start and end of
     * the node. The links are its parent, its first child and its next
     * sibling.
     *
     * @param array{int, int} $offsets
     * @param array{int, int, int} $links
     */
    private static function record(NodeKind $kind, array $offsets, array $links): string
    {
        return pack('CNNNNN', array_search($kind, self::KINDS, strict: true), ...$offsets, ...$links);
    }
}
