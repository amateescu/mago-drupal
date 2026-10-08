<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\PHPStanIgnores;
use PHPUnit\Framework\TestCase;

use function strpos;

final class PHPStanIgnoresTest extends TestCase
{
    /**
     * A comment on its own line covers the next code, past other comments,
     * and a trailing one covers its own line.
     */
    public function testCoversTheNextCodeOrItsOwnLine(): void
    {
        $code = <<<'PHP'
            <?php
            function example(): int {
                // @phpstan-ignore return.type
                return 'first';
                // @phpstan-ignore argument.type, return.type (a reason, with a comma)
                // @mago-expect analysis:invalid-return-statement
                return 'second';
                strlen(1); // @phpstan-ignore argument.type
                return 'third';
                /** @phpstan-ignore method.notFound */
                $this->missing();
            }
            PHP;

        $ignores = PHPStanIgnores::of($code);

        self::assertNotNull($ignores);
        self::assertSame(['return.type'], $ignores->at(self::at($code, "return 'first'")));
        self::assertSame(['argument.type', 'return.type'], $ignores->at(self::at($code, "return 'second'")));
        self::assertSame(['argument.type'], $ignores->at(self::at($code, 'strlen(1)')));
        self::assertNull($ignores->at(self::at($code, "return 'third'")));
        self::assertSame(['method.notFound'], $ignores->at(self::at($code, '$this->missing()')));
        self::assertNull($ignores->at(self::at($code, 'function example')));
    }

    /**
     * The line forms ignore everything, whatever text follows the tag.
     */
    public function testLineFormsIgnoreEveryIdentifier(): void
    {
        $code = <<<'PHP'
            <?php
            // @phpstan-ignore-next-line property.notFound
            echo $object->first;
            echo $object->second;
            echo $object->third; // @phpstan-ignore-line
            /**
             * @phpstan-ignore-next-line
             */
            echo $object->fourth;
            PHP;

        $ignores = PHPStanIgnores::of($code);

        self::assertNotNull($ignores);
        self::assertTrue($ignores->at(self::at($code, 'echo $object->first')));
        self::assertNull($ignores->at(self::at($code, 'echo $object->second')));
        self::assertTrue($ignores->at(self::at($code, 'echo $object->third')));
        self::assertTrue($ignores->at(self::at($code, 'echo $object->fourth')));
    }

    /**
     * A tag without identifiers, a longer tag name and a tag in a string
     * cover nothing.
     */
    public function testIgnoresWhatIsNotAnIgnoreComment(): void
    {
        self::assertNull(PHPStanIgnores::of("<?php\necho 'x';\n"));

        $code = <<<'PHP'
            <?php
            // @phpstan-ignore
            echo 'first';
            // @phpstan-ignore-everything return.type
            echo 'second';
            echo '@phpstan-ignore-line';
            PHP;

        self::assertNull(PHPStanIgnores::of($code));
    }

    private static function at(string $code, string $marker): int
    {
        $offset = strpos($code, $marker);
        self::assertIsInt($offset, $marker);

        return $offset;
    }
}
