<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\ClassLikeRanges;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClassLikeRangesTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function declarations(): iterable
    {
        yield 'a line of its own' => ["<?php\n\nfinal class Foo {}\n", true];
        yield 'after an attribute on its line' => ["<?php\n\n#[\\Attribute] final class Foo {}\n", true];
        yield 'after a comment on its line' => ["<?php\n\n/* Old. */ abstract class Foo {}\n", true];
        yield 'a comment between its tokens' => ["<?php\n\nfinal /* x */ readonly # y\nclass\n// z\nFoo {}\n", true];
        yield 'after the open tag' => ['<?php interface Foo {}', true];
        yield 'after another statement' => ["<?php\n\nfoo(); enum Foo {}\n", true];
        yield 'keywords in any case' => ["<?php\n\nFINAL CLASS Foo {}\n", true];
        yield 'a procedural file' => ["<?php\n\nfunction foo(): string {\n  return Foo::class;\n}\n", false];
        yield 'an anonymous class' => ["<?php\n\n\$foo = new class {};\n", false];
        yield 'a named argument' => ["<?php\n\nfoo(class: 'Foo');\n", false];
    }

    #[DataProvider('declarations')]
    public function testScreensTheFilesThatCanDeclareAClassLike(string $contents, bool $declares): void
    {
        self::assertSame($declares, ClassLikeRanges::mayDeclare($contents));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function anonymous(): iterable
    {
        yield 'new class' => ['<?php $a = new class {};', true];
        yield 'an attribute first' => ['<?php $a = new #[A] class {};', true];
        yield 'readonly first' => ['<?php $a = new readonly class {};', true];
        yield 'a comment between' => ["<?php \$a = new /* x */ class {};\n\$b = new // y\nclass {};", true];
        yield 'keywords in any case' => ['<?php $a = NEW CLASS {};', true];
        yield 'a named class' => ['<?php $a = new Foo();', false];
        yield 'a name that starts with the keyword' => ['<?php $a = new classes();', false];
    }

    #[DataProvider('anonymous')]
    public function testScreensTheFilesThatCanHoldAnAnonymousClass(string $contents, bool $holds): void
    {
        self::assertSame($holds, ClassLikeRanges::mayHoldAnonymous($contents));
    }
}
