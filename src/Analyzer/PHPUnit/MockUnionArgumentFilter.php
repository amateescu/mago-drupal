<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\TypeComparator;

use function in_array;
use function preg_match;
use function strlen;
use function substr;

/**
 * Drops the argument report for an `X|MockObject` passed where `X` goes.
 *
 * Tests document mocks as `X|MockObject`, from before PHP had intersection
 * types, and core's `UnitTestCase` helpers return them that way too. The
 * value is both at runtime, and phpstan-phpunit reads the union as
 * `X&MockObject`. Mago checks each half on its own and reports the
 * `MockObject` half as a possibly invalid argument. The report goes when
 * every other member of the passed union is the expected type or a class
 * that fits it. A `null` member does not keep it, since Mago reports that as
 * a possibly null argument on its own; any other type does.
 *
 * @internal
 */
final class MockUnionArgumentFilter implements IssueFilterHook
{
    private const MOCK_OBJECT = 'PHPUnit\Framework\MockObject\MockObject';

    /**
     * Members the filter passes over: the mock half, and null, which Mago
     * reports as `possibly-null-argument`.
     */
    private const SKIPPED = [self::MOCK_OBJECT, 'null'];

    /**
     * The expected and the passed type, as Mago words the report.
     */
    private const MESSAGE = '/expected `(?<expected>[^`]+)`, but possibly received `(?<received>[^`]+)`\.$/';

    private const CLASS_NAME = '/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)+$/';

    public function getCodes(): array
    {
        return ['possibly-invalid-argument'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $matches = [];
        if (preg_match(self::MESSAGE, $context->issue->message, $matches) !== 1) {
            return IssueFilterDecision::Keep;
        }

        $received = self::members($matches['received']);
        if (!in_array(self::MOCK_OBJECT, $received, strict: true)) {
            return IssueFilterDecision::Keep;
        }

        $expected = self::members($matches['expected']);
        $others = 0;
        foreach ($received as $member) {
            if (in_array($member, self::SKIPPED, strict: true)) {
                continue;
            }

            if (!self::fits($context->types, $member, $expected)) {
                return IssueFilterDecision::Keep;
            }

            $others++;
        }

        return $others > 0 ? IssueFilterDecision::Remove : IssueFilterDecision::Keep;
    }

    /**
     * Whether one member of the passed union is a member of the expected
     * type, or a class that fits one of its classes.
     *
     * @param list<string> $expected
     */
    private static function fits(TypeComparator $types, string $member, array $expected): bool
    {
        if (in_array($member, $expected, strict: true)) {
            return true;
        }

        if (preg_match(self::CLASS_NAME, $member) !== 1) {
            return false;
        }

        foreach ($expected as $class) {
            if (
                preg_match(self::CLASS_NAME, $class) === 1
                && $types->isContainedBy(Type::namedObject($member), Type::namedObject($class))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The top-level members of a union as Mago prints it, keeping the `|`
     * inside generics, shapes and callables together.
     *
     * @return list<string>
     */
    private static function members(string $type): array
    {
        $members = [];
        $depth = 0;
        $start = 0;
        $length = strlen($type);
        for ($i = 0; $i < $length; $i++) {
            $depth += match ($type[$i]) {
                '<', '{', '(', '[' => 1,
                '>', '}', ')', ']' => -1,
                default => 0,
            };
            if ($depth === 0 && $type[$i] === '|') {
                $members[] = substr($type, $start, $i - $start);
                $start = $i + 1;
            }
        }

        $members[] = substr($type, $start);

        return $members;
    }
}
