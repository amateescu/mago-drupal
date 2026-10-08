<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\Linter\Rules\TodoCurrentIssueRule;
use PHPUnit\Framework\TestCase;

final class TodoCurrentIssueRuleTest extends TestCase
{
    /**
     * The branch name wins over the issue fork's path.
     */
    public function testReadsTheIssueOfTheMergeRequest(): void
    {
        self::assertSame(3_456_789, TodoCurrentIssueRule::issue('3456789-fix-thing', null));
        self::assertSame(3_456_789, TodoCurrentIssueRule::issue(null, 'issue/mymodule-3456789'));
        self::assertSame(1, TodoCurrentIssueRule::issue('1-first', 'issue/mymodule-2'));
        self::assertSame(2, TodoCurrentIssueRule::issue('fix-thing', 'issue/mymodule-2'));
    }

    public function testNothingOutsideAMergeRequest(): void
    {
        self::assertNull(TodoCurrentIssueRule::issue(null, null));
        self::assertNull(TodoCurrentIssueRule::issue('main', 'project/mymodule'));
    }
}
