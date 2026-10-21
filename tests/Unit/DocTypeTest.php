<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\Docblock\DocType;
use PHPUnit\Framework\TestCase;

final class DocTypeTest extends TestCase
{
    public function testSplitSeparatesTheLeadingTypeFromTheRest(): void
    {
        self::assertSame(['string', '$foo The description.'], DocType::split('string $foo The description.'));
        self::assertSame(['bool', ''], DocType::split('bool'));
        self::assertSame([null, ''], DocType::split(''));
    }
}
