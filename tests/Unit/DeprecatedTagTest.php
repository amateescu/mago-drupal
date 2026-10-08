<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\DeprecatedTag;
use PHPUnit\Framework\TestCase;

use function strpos;

final class DeprecatedTagTest extends TestCase
{
    /**
     * A method's span starts at its attributes, so the docblock sits right
     * above it, and the tag's continuation lines join into one.
     */
    public function testReadsTheTagAboveAMethod(): void
    {
        $code = <<<'PHP'
            <?php
            class A {
              /**
               * Does a thing.
               *
               * @deprecated in drupal:11.4.0 and is removed from drupal:13.0.0. Use
               *   b() instead.
               *
               * @see https://www.drupal.org/node/1
               */
              #[\ReturnTypeWillChange]
              public function a() {}
            }
            PHP;

        $text = DeprecatedTag::above($code, self::offset($code, '#['));
        self::assertSame('in drupal:11.4.0 and is removed from drupal:13.0.0. Use b() instead.', $text);
    }

    /**
     * A global constant's span starts at its name.
     */
    public function testReadsTheTagAboveAGlobalConstant(): void
    {
        $code = <<<'PHP'
            <?php
            /**
             * @deprecated in drupal:11.3.0 and is removed from drupal:13.0.0.
             */
            const DRUPAL_DISABLED = 0;
            PHP;

        $text = DeprecatedTag::above($code, self::offset($code, 'DRUPAL_DISABLED'));
        self::assertSame('in drupal:11.3.0 and is removed from drupal:13.0.0.', $text);
    }

    public function testNothingWithoutADocblockRightAbove(): void
    {
        $code = <<<'PHP'
            <?php
            /**
             * @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0.
             */
            function a() {}
            function b() {}
            /** @deprecated in drupal:11.3.0 and is removed from drupal:12.0.0. */
            function c() {}
            /* A plain comment. */
            function d() {}
            /** Not deprecated, and {@deprecated} inline does not count. */
            function e() {}
            PHP;

        self::assertNull(DeprecatedTag::above($code, self::offset($code, 'function b')));
        self::assertNull(DeprecatedTag::above($code, self::offset($code, 'function d')));
        self::assertNull(DeprecatedTag::above($code, self::offset($code, 'function e')));
        $text = DeprecatedTag::above($code, self::offset($code, 'function c'));
        self::assertSame('in drupal:11.3.0 and is removed from drupal:12.0.0.', $text);
    }

    /**
     * The hook scan hands over a docblock without its delimiters.
     */
    public function testReadsTheTagToTheEndOfAnOpenDocblock(): void
    {
        self::assertSame(
            'in drupal:11.1.0 and is removed from drupal:12.0.0.',
            DeprecatedTag::text(
                "\n * An old hook.\n *\n * @deprecated in drupal:11.1.0 and is removed from drupal:12.0.0.\n ",
            ),
        );
    }

    private static function offset(string $code, string $needle): int
    {
        $offset = strpos($code, $needle);
        self::assertIsInt($offset);

        return $offset;
    }
}
