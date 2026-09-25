<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Internal\HookFunctions;
use PHPUnit\Framework\TestCase;

use function dirname;

final class HookFunctionsTest extends TestCase
{
    public function testReadsDeprecationAndParameterCountsFromApiFiles(): void
    {
        $hooks = HookFunctions::fromApiFiles([dirname(__DIR__) . '/fixtures/api/sample.api.php']);

        self::assertSame(6, $hooks->count());
        self::assertFalse($hooks->deprecation('hook_form_alter'));
        self::assertSame(3, $hooks->parameterCount('hook_form_alter'));
        self::assertSame(3, $hooks->parameterCount('hook_with_defaults'));
        self::assertSame(0, $hooks->parameterCount('hook_bare'));
        self::assertTrue($hooks->deprecation('HOOK_OLD'));
        self::assertSame(1, $hooks->parameterCount('hook_old'));
        self::assertNull($hooks->deprecation('hook_unknown'));
        self::assertNull($hooks->parameterCount('_api_helper'));
        // A docblock ends at its own `*/`: the helper's @deprecated stays with
        // the helper.
        self::assertFalse($hooks->deprecation('hook_after_helper'));
        // Core puts `// phpcs:` lines between some docblocks and functions.
        self::assertFalse($hooks->deprecation('hook_update_N'));
        self::assertSame(1, $hooks->parameterCount('hook_update_N'));
    }

    public function testSkipsUnreadableFiles(): void
    {
        self::assertSame(0, HookFunctions::fromApiFiles([dirname(__DIR__) . '/fixtures/api/missing.api.php'])->count());
    }
}
