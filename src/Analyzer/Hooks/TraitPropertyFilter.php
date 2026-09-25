<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\AnalysisMemo;
use amateescu\MagoDrupal\Internal\TraitRoots;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Analyzer\Metadata\MemberIdentifier;

use function array_map;
use function in_array;
use function preg_match;
use function strtolower;

/**
 * Drops the missing property report for a property on a trait's `$this`
 * that every class using the trait has.
 *
 * Mago analyzes a trait once, on its own, so `$this->container` in a trait
 * that does not declare it is a missing property, although every class using
 * the trait has one. PHPStan checks the trait's body in each class instead.
 * A declared property and a `@property` tag both count. The report is only
 * dropped, the property is not typed: the SDK types a property only as a
 * magic one, which on a trait without `__get()` is reported in turn. So the
 * value stays `mixed`, and what the code does with it can still be reported.
 *
 * @internal
 */
final class TraitPropertyFilter implements IssueFilterHook
{
    /**
     * The property and the trait, as Mago words the report.
     */
    private const MESSAGE = '/^(?:Static p|P)roperty `\$(?<property>\w+)` does not exist on trait `(?<trait>[^`]+)`\.$/';

    /**
     * Whether every class using the trait has the property, by lowercased
     * trait name and property name.
     *
     * @var AnalysisMemo<bool>
     */
    private readonly AnalysisMemo $covered;

    public function __construct(
        private readonly TraitRoots $roots,
    ) {
        $this->covered = new AnalysisMemo();
    }

    public function getCodes(): array
    {
        return ['non-existent-property'];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $matches = [];
        if (preg_match(self::MESSAGE, $context->issue->message, $matches) !== 1) {
            return IssueFilterDecision::Keep;
        }

        $codebase = $context->codebase;
        $trait = $matches['trait'];
        $property = $matches['property'];
        $covered = $this->covered->get(
            $codebase,
            strtolower($trait) . "\0" . $property,
            fn(): bool => self::everyRootHas($codebase, $this->roots->of($codebase, $trait), $property),
        );

        return $covered ? IssueFilterDecision::Remove : IssueFilterDecision::Keep;
    }

    /**
     * Whether some class uses the trait and every root among them has the
     * property, declared or through a `@property` tag. Their subclasses
     * have it too.
     *
     * @param list<string> $classes See TraitRoots.
     */
    private static function everyRootHas(Codebase $codebase, array $classes, string $property): bool
    {
        if ($classes === []) {
            return false;
        }

        $members = array_map(
            static fn(string $class): MemberIdentifier => new MemberIdentifier($class, '$' . $property),
            $classes,
        );
        $declared = $codebase->checkMultiplePropertiesExist($members);
        $missing = [];
        foreach ($declared as $index => $exists) {
            if ($exists) {
                continue;
            }

            $missing[] = $members[$index];
        }

        return !in_array(false, $codebase->checkMultipleMagicPropertiesExist($missing), strict: true);
    }
}
