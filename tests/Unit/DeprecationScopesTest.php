<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DeprecationScopes;
use Mago\Sdk\Span;
use PHPUnit\Framework\TestCase;

use function strpos;

final class DeprecationScopesTest extends TestCase
{
    private const TEST = 'modules/legacy/tests/src/Unit/LegacyTest.php';

    private const RUNTIME = 'modules/legacy/src/Legacy.php';

    /**
     * A legacy group marks a scope in a test and nowhere else.
     */
    private const LEGACY = <<<'PHP'
        <?php
        /**
         * @group legacy
         */
        class A {
            public function b(): void { /* MARKED */ }
        }
        PHP;

    public function testLegacyGroupMarksTestFilesOnly(): void
    {
        self::assertTrue(DeprecationScopes::marked(self::LEGACY, self::TEST));
        self::assertTrue(DeprecationScopes::of(self::LEGACY, self::TEST)->covers(self::at(
            self::LEGACY,
            '/* MARKED */',
        )));

        self::assertFalse(DeprecationScopes::marked(self::LEGACY, self::RUNTIME));
        self::assertFalse(DeprecationScopes::of(self::LEGACY, self::RUNTIME)->covers(self::at(
            self::LEGACY,
            '/* MARKED */',
        )));
    }

    /**
     * The same bytes at a test path and at another path are two results,
     * whichever comes first.
     */
    public function testRemembersTestAndRuntimeScopesApart(): void
    {
        $marked = self::at(self::LEGACY, '/* MARKED */');

        self::assertFalse(DeprecationScopes::of(self::LEGACY, self::RUNTIME)->covers($marked));
        self::assertTrue(DeprecationScopes::of(self::LEGACY, self::TEST)->covers($marked));
        self::assertFalse(DeprecationScopes::of(self::LEGACY, self::RUNTIME)->covers($marked));
    }

    /**
     * The other markers do not depend on the path.
     */
    public function testOtherMarkersWorkOutsideTests(): void
    {
        $code = <<<'PHP'
            <?php
            class A {
                #[IgnoreDeprecations]
                public function ignored(): void { /* IGNORED */ }
                /** @deprecated in drupal:11.4.0 and is removed from drupal:12.0.0. */
                public function retired(): void { /* RETIRED */ }
            }
            PHP;

        self::assertTrue(DeprecationScopes::marked($code, self::RUNTIME));
        $scopes = DeprecationScopes::of($code, self::RUNTIME);

        self::assertTrue($scopes->covers(self::at($code, '/* IGNORED */')));
        self::assertTrue($scopes->covers(self::at($code, '/* RETIRED */')));
    }

    public function testUnmarkedFileNeedsNoScan(): void
    {
        self::assertFalse(DeprecationScopes::marked('<?php class A { public function b(): void {} }', self::TEST));
        self::assertTrue(DeprecationScopes::marked("<?php\n/** @group legacy */\nclass A {}", self::TEST));
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

        $scopes = DeprecationScopes::of($code, self::TEST);

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

        self::assertFalse(DeprecationScopes::of($code, self::TEST)->covers(self::at($code, '/* PLAIN */')));
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

        self::assertTrue(DeprecationScopes::marked($code, self::TEST));
        $scopes = DeprecationScopes::of($code, self::TEST);

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

        $scopes = DeprecationScopes::of($code, self::TEST);

        self::assertTrue($scopes->covers(self::at($code, '/* MARKED */')));
        self::assertFalse($scopes->covers(self::at($code, '/* PLAIN */')));
    }

    public function testBrokenPhpYieldsNoScopes(): void
    {
        self::assertFalse(DeprecationScopes::of('<?php class {{{ @group legacy', self::TEST)->covers(new Span(0, 1)));
    }

    private static function at(string $code, string $marker): Span
    {
        $offset = strpos($code, $marker);
        self::assertIsInt($offset, $marker);

        return new Span($offset, $offset + 1);
    }
}
