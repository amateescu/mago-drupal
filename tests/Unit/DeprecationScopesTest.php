<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DeprecationScopes;
use Mago\Sdk\Span;
use PHPUnit\Framework\TestCase;

use function strpos;

final class DeprecationScopesTest extends TestCase
{
    public function testUnmarkedFileNeedsNoScan(): void
    {
        self::assertFalse(DeprecationScopes::marked('<?php class A { public function b(): void {} }'));
        self::assertTrue(DeprecationScopes::marked("<?php\n/** @group legacy */\nclass A {}"));
    }

    /**
     * A marked method ends at its own closing brace, braces in strings and
     * bodies included.
     */
    public function testScopeStopsAtTheEndOfTheMarkedMethod(): void
    {
        $code = <<<'PHP'
            <?php
            class A {
                #[IgnoreDeprecations]
                public function marked(): void {
                    if (true) { $x = "{$this->y} brace"; /* MARKED */ }
                }
                public function plain(): void { /* PLAIN */ }
            }
            PHP;

        $scopes = DeprecationScopes::of($code);

        self::assertTrue($scopes->covers(self::at($code, '/* MARKED */')));
        self::assertFalse($scopes->covers(self::at($code, '/* PLAIN */')));
    }

    /**
     * An abstract method has no body, so its scope ends at the semicolon.
     */
    public function testDeclarationWithoutABodyEndsAtTheSemicolon(): void
    {
        $code = <<<'PHP'
            <?php
            abstract class A {
                /** @group legacy */
                abstract public function marked(): void;
                public function plain(): void { /* PLAIN */ }
            }
            PHP;

        self::assertFalse(DeprecationScopes::of($code)->covers(self::at($code, '/* PLAIN */')));
    }

    /**
     * A deprecated method covers its body. A deprecated property opens no
     * scope, so the method after it is still checked.
     */
    public function testDeprecatedDeclarationCoversItsBody(): void
    {
        $code = <<<'PHP'
            <?php
            class A {
                /** @deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. */
                public function retired(): void { /* MARKED */ }
                /** @deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. */
                protected ?int $old = null;
                public function plain(): void { /* PLAIN */ }
            }
            PHP;

        self::assertTrue(DeprecationScopes::marked($code));
        $scopes = DeprecationScopes::of($code);

        self::assertTrue($scopes->covers(self::at($code, '/* MARKED */')));
        self::assertFalse($scopes->covers(self::at($code, '/* PLAIN */')));
    }

    public function testHelperCallCoversItsArgumentsOnly(): void
    {
        $code = <<<'PHP'
            <?php
            class A {
                public function b(): void {
                    \Drupal\Component\Utility\DeprecationHelper::backwardsCompatibleCall(
                        currentVersion: '11.4.0',
                        deprecatedVersion: '11.2.0',
                        currentCallable: static fn() => null,
                        deprecatedCallable: static fn() => retired(/* MARKED */),
                    );
                    other(/* PLAIN */);
                }
            }
            PHP;

        $scopes = DeprecationScopes::of($code);

        self::assertTrue($scopes->covers(self::at($code, '/* MARKED */')));
        self::assertFalse($scopes->covers(self::at($code, '/* PLAIN */')));
    }

    public function testBrokenPhpYieldsNoScopes(): void
    {
        self::assertFalse(DeprecationScopes::of('<?php class {{{ @group legacy')->covers(new Span(0, 1)));
    }

    private static function at(string $code, string $marker): Span
    {
        $offset = strpos($code, $marker);
        self::assertIsInt($offset, $marker);

        return new Span($offset, $offset + 1);
    }
}
