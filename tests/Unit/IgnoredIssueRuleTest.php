<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Tests;

use amateescu\MagoDrupal\DrupalExtension;
use amateescu\MagoDrupal\Internal\Ignore\PhpcsCommentFilter;
use amateescu\MagoDrupal\Internal\Ignore\PHPStanCommentFilter;
use amateescu\MagoDrupal\Internal\IgnoredIssueRule;
use Mago\Sdk\Extension;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use PHPUnit\Framework\TestCase;

use function array_map;
use function strlen;
use function strpos;
use function substr;

final class IgnoredIssueRuleTest extends TestCase
{
    private const CODE = <<<'PHP'
        <?php
        // phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
        $first = 1;
        // phpcs:ignore Drupal.Files.LineLength
        $second = 2;
        // @phpstan-ignore renderCallback.nonTrustedCallback
        $third = 3;
        // @phpstan-ignore-next-line
        $fourth = 4;
        $fifth = 5;
        PHP;

    /**
     * A phpcs comment drops an issue when it names a code of the rule, and
     * keeps the others.
     */
    public function testPhpcsCommentsDropTheIssuesOfTheirCodes(): void
    {
        $rule = new IgnoredIssueRule(self::reporter(), PhpcsCommentFilter::for('drupal/property-name'), null);

        self::assertSame(['$second', '$third', '$fourth', '$fifth'], self::reported($rule));
    }

    /**
     * A PHPStan comment drops an issue when it names an identifier of the
     * rule, or doesn't name any.
     */
    public function testPHPStanCommentsDropTheIssuesOfTheirIdentifiers(): void
    {
        $rule = new IgnoredIssueRule(self::reporter(), null, PHPStanCommentFilter::for('drupal/render-callback'));

        self::assertSame(['$first', '$second', '$fifth'], self::reported($rule));
    }

    /**
     * The worker arguments turn the comments on, and without them every
     * issue stays.
     */
    public function testWorkerArgumentsTurnTheCommentsOn(): void
    {
        $code = "\xEF\xBB\xBF<?php // phpcs:ignoreFile\n";

        self::assertCount(1, ProgramLint::run(self::byteOrderMark(DrupalExtension::fromArguments([])), $code));
        self::assertCount(0, ProgramLint::run(
            self::byteOrderMark(DrupalExtension::fromArguments(['--phpcs-ignores'])),
            $code,
        ));
    }

    /**
     * The extension's byte-order-mark rule, which targets the whole file.
     */
    private static function byteOrderMark(Extension $extension): Rule
    {
        foreach ($extension->linterRules as $rule) {
            if ($rule->getDefinition()->code === 'drupal/byte-order-mark') {
                return $rule;
            }
        }

        self::fail('drupal/byte-order-mark is not registered.');
    }

    /**
     * The variables that the rule's issues start at.
     *
     * @return list<string>
     */
    private static function reported(Rule $rule): array
    {
        return array_map(
            static fn(Issue $issue): string => substr(
                self::CODE,
                $issue->annotations[0]->span->start,
                $issue->annotations[0]->span->end - $issue->annotations[0]->span->start,
            ),
            ProgramLint::run($rule, self::CODE),
        );
    }

    /**
     * A rule that reports each of the five variables.
     */
    private static function reporter(): Rule
    {
        return new class implements Rule {
            public function getDefinition(): RuleDefinition
            {
                return new RuleDefinition(
                    'drupal/test',
                    'Test',
                    'Reports each variable.',
                    Level::Error,
                    true,
                    [NodeKind::Program],
                );
            }

            public function lint(LintContext $context): void
            {
                foreach (['$first', '$second', '$third', '$fourth', '$fifth'] as $variable) {
                    $start = (int) strpos($context->file->contents, $variable);
                    $context->report(Issue::new('Reported.', new Span($start, $start + strlen($variable))));
                }
            }
        };
    }
}
