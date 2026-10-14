<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Analyzer\Hooks\StubFiles;
use Closure;
use Mago\Sdk\Analyzer\InitializationContext;
use Mago\Sdk\CancellationTokenInterface;
use Mago\Sdk\PHPVersion;
use PHPUnit\Framework\TestCase;

use function basename;
use function file_get_contents;
use function token_get_all;

use const TOKEN_PARSE;

final class StubFilesTest extends TestCase
{
    public function testEveryShippedStubIsValidPhp(): void
    {
        $files = (new StubFiles())->files();

        self::assertNotSame([], $files);
        foreach ($files as $file) {
            $code = file_get_contents($file);

            self::assertIsString($code, $file);
            // TOKEN_PARSE runs the real parser, so a broken stub throws here
            // instead of being dropped silently by the host.
            self::assertNotSame([], token_get_all(code: $code, flags: TOKEN_PARSE), $file);
        }
    }

    public function testHookAddsEveryStubUnderItsBaseName(): void
    {
        $context = new InitializationContext(PHPVersion::fromParts(major: 8, minor: 1), self::cancellation());

        (new StubFiles())->initialize($context);

        $added = [];
        foreach ($context->getStubs() as [$name, $bytes]) {
            $added[$name] = $bytes;
        }

        $expected = [];
        foreach ((new StubFiles())->files() as $file) {
            $expected[] = basename($file);
        }

        self::assertSame($expected, array_keys($added));
        foreach ($added as $name => $bytes) {
            self::assertNotSame('', $bytes, $name);
        }
    }

    public function testMissingDirectoryAddsNothing(): void
    {
        $context = new InitializationContext(PHPVersion::fromParts(major: 8, minor: 1), self::cancellation());

        (new StubFiles('/nonexistent/mago-drupal/stubs'))->initialize($context);

        self::assertSame([], $context->getStubs());
    }

    private static function cancellation(): CancellationTokenInterface
    {
        return new class implements CancellationTokenInterface {
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
    }

    public function testFollowsTheInstalledCoreRelease(): void
    {
        $stub = <<<'PHP'
            <?php

            interface Api
            {
                /**
                 * Goes away.
                 *
                 * @deprecated in drupal:11.2.0 and is removed from drupal:12.0.0. There is
                 *   no replacement.
                 * @stub-deprecated-in 11.2
                 * @stub-removed-in 12.0
                 */
                public function old(): void;

                public function kept(): void;
            }
            PHP;

        // Before the deprecation, the member stays without its tag.
        $before = StubFiles::forVersion($stub, '11.1.3');
        self::assertStringContainsString('function old()', $before);
        self::assertStringNotContainsString('@deprecated', $before);

        $deprecated = StubFiles::forVersion($stub, '11.2.0');
        self::assertStringContainsString('function old()', $deprecated);
        self::assertStringContainsString('@deprecated', $deprecated);

        $removed = StubFiles::forVersion($stub, '12.0.0');
        self::assertStringNotContainsString('function old()', $removed);
        self::assertStringContainsString('function kept()', $removed);

        // Without a core to read the release from, the stub is used as written.
        self::assertSame($stub, StubFiles::forVersion($stub, null));
    }
}
