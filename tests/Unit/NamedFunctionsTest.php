<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\NamedFunctions;
use PHPUnit\Framework\TestCase;

use function strpos;

final class NamedFunctionsTest extends TestCase
{
    /**
     * A closure belongs to the method it sits in, and a function outside a
     * class has no class-like.
     */
    public function testFindsTheMethodAndItsClassLike(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Drupal\example;
            final class Decorator {
                /** {@inheritdoc} */
                public function invalidateAll(): void {
                    $callback = function () { /* CLOSURE */ };
                    /* METHOD */
                }
            }
            function helper(): void { /* FUNCTION */ }
            PHP;

        $functions = NamedFunctions::of($code);

        $method = ['invalidateAll', '/** {@inheritdoc} */', 'Drupal\example\Decorator'];
        self::assertSame($method, $functions->at(self::at($code, '/* METHOD */')));
        self::assertSame($method, $functions->at(self::at($code, '/* CLOSURE */')));
        self::assertSame(['helper', '', null], $functions->at(self::at($code, '/* FUNCTION */')));
        self::assertNull($functions->at(self::at($code, 'namespace')));
    }

    /**
     * `Foo::class` declares nothing, and a method of an anonymous class has
     * no named class-like.
     */
    public function testLeavesOutClassConstantsAndAnonymousClasses(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Drupal\example;
            final class Outer {
                public function build(): object {
                    $name = Outer::class;
                    return new class {
                        public function inner(): void { /* ANONYMOUS */ }
                    };
                }
                public function after(): void { /* AFTER */ }
            }
            PHP;

        $functions = NamedFunctions::of($code);

        self::assertSame(['inner', '', null], $functions->at(self::at($code, '/* ANONYMOUS */')));
        self::assertSame(['after', '', 'Drupal\example\Outer'], $functions->at(self::at($code, '/* AFTER */')));
    }

    public function testReadsBracedNamespaces(): void
    {
        $code = <<<'PHP'
            <?php
            namespace Drupal\first {
                trait Forwarding { public function a(): void { /* FIRST */ } }
            }
            namespace {
                interface Plain { public function b(): void; }
                enum Suit { case Hearts; public function c(): void { /* GLOBAL */ } }
            }
            PHP;

        $functions = NamedFunctions::of($code);

        self::assertSame(['a', '', 'Drupal\first\Forwarding'], $functions->at(self::at($code, '/* FIRST */')));
        self::assertSame(['c', '', 'Suit'], $functions->at(self::at($code, '/* GLOBAL */')));
    }

    private static function at(string $code, string $marker): int
    {
        $offset = strpos($code, $marker);
        self::assertIsInt($offset, $marker);

        return $offset;
    }
}
