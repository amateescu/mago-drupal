<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Linter\Rules\RequestSuperglobalRule;
use Closure;
use Mago\Sdk\CancellationTokenInterface;
use Mago\Sdk\Internal\Syntax\NodeStore;
use Mago\Sdk\Internal\Syntax\ResolvedNameStore;
use Mago\Sdk\Internal\Syntax\TriviaStore;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use PHPUnit\Framework\TestCase;

use function pack;
use function strlen;
use function strpos;

/**
 * An editor session lints the same path again after every edit, and one
 * worker keeps its rules for the whole session.
 */
final class FileGateTest extends TestCase
{
    private const NO_NODE = 4_294_967_295;

    public function testANewSnapshotOfTheSamePathIsReadAgain(): void
    {
        $gate = new FileGate(needles: ['$_GET']);

        self::assertFalse($gate->passes(self::sourceFile('<?php echo $safe;')));
        self::assertTrue($gate->passes(self::sourceFile('<?php echo $_GET;')));
        self::assertFalse($gate->passes(self::sourceFile('<?php echo $safe;')));
    }

    public function testAPatternGateReadsANewSnapshotAgain(): void
    {
        $gate = new FileGate(pattern: '/\bmd5\s*\(/');

        self::assertTrue($gate->passes(self::sourceFile('<?php md5($a);')));
        self::assertFalse($gate->passes(self::sourceFile('<?php sha1($a);')));
    }

    public function testARuleReportsTheSecondSnapshotOfTheSamePath(): void
    {
        $rule = new RequestSuperglobalRule();

        self::assertSame([], self::lint($rule, '<?php echo $safe;', '$safe'));
        self::assertCount(1, self::lint($rule, '<?php echo $_GET;', '$_GET'));
    }

    /**
     * Lints a snapshot whose Program node holds one direct variable.
     *
     * @return list<\Mago\Sdk\Reporting\Issue>
     */
    private static function lint(RequestSuperglobalRule $rule, string $contents, string $variable): array
    {
        $start = (int) strpos($contents, $variable);
        $records =
            pack('CNNNNN', 0, 0, strlen($contents), self::NO_NODE, 1, self::NO_NODE)
            . pack('CNNNNN', 1, $start, $start + strlen($variable), 0, self::NO_NODE, self::NO_NODE);
        $file = self::sourceFile($contents, new NodeStore([NodeKind::Program, NodeKind::DirectVariable], $records, 2));

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

    private static function sourceFile(string $contents, ?NodeStore $nodes = null): SourceFile
    {
        return new SourceFile(
            PHPVersion::fromParts(major: 8, minor: 1),
            'modules/corpus/corpus.module',
            $contents,
            [0],
            $nodes ?? new NodeStore([], '', 0),
            new ResolvedNameStore('', '', '', 0),
            new TriviaStore('', 0),
            null,
        );
    }
}
