<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;

use function getenv;
use function is_string;
use function preg_match;
use function stripos;
use function strlen;
use function strpos;

/**
 * Reports a `@todo` comment that links the issue of the current merge
 * request.
 *
 * Ports phpstan-drupal's TodoCommentWithIssueUrlRule. A to-do that points at
 * the issue being worked on is work the merge request leaves undone. The issue
 * number comes from GitLab CI: the start of the source branch name, as in
 * `3456789-fix-thing`, or the end of the issue fork's project path, as in
 * `issue/mymodule-3456789`. Outside a merge request pipeline neither is set
 * and the rule reports nothing, so unlike phpstan-drupal's rule it is on by
 * default.
 */
final class TodoCurrentIssueRule implements Rule
{
    /**
     * A drupal.org issue link and its node ID.
     */
    private const LINK = '#drupal\.org/(?:i/|node/|project/[^/\s]+/issues/)(\d+)#i';

    private bool $detected = false;

    private ?int $issue = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/todo-current-issue',
            name: 'To-do for the current issue',
            description: 'Reports a @todo comment that links the issue of the current GitLab merge request.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!$this->detected) {
            $this->issue = self::issue(
                self::variable('CI_MERGE_REQUEST_SOURCE_BRANCH_NAME'),
                self::variable('CI_MERGE_REQUEST_SOURCE_PROJECT_PATH'),
            );
            $this->detected = true;
        }

        if ($this->issue === null || stripos($context->file->contents, needle: '@todo') === false) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            $text = $context->file->getText($trivia->span);
            if (stripos($text, needle: '@todo') !== false) {
                $this->check($context, $text, $trivia->span->start);
            }
        }
    }

    /**
     * Reports each link to the current issue in a comment's text.
     */
    private function check(LintContext $context, string $text, int $base): void
    {
        $offset = 0;
        $match = [];
        while (preg_match(self::LINK, $text, $match, offset: $offset) === 1) {
            $link = $match[0];
            $at = strpos($text, $link, $offset);
            if ($at === false) {
                return;
            }

            $offset = $at + strlen($link);
            if ((int) $match[1] === $this->issue) {
                $context->report(Issue::new(
                    "This @todo links the issue of the current merge request, #{$this->issue}.",
                    new Span($base + $at, $base + $offset),
                )->withHelp('Resolve it in this merge request, or link a follow-up issue.'));
            }
        }
    }

    /**
     * The issue number GitLab CI gives a merge request: the digits that start
     * the source branch name, or those that end the issue fork's path.
     */
    public static function issue(?string $branch, ?string $projectPath): ?int
    {
        $matches = [];
        if ($branch !== null && preg_match('/^(\d+)/', $branch, $matches) === 1) {
            return (int) $matches[1];
        }

        if ($projectPath !== null && preg_match('/-(\d+)$/', $projectPath, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private static function variable(string $name): ?string
    {
        $value = getenv($name);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
