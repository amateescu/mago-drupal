<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\FileGate;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;
use function strlen;

/**
 * Reports a to-do comment that does not start with `@todo`.
 *
 * Ports Drupal.Commenting.TodoCommentSniff. Catches the wording variants
 * Drupal's release tooling would otherwise miss when scanning for open
 * to-dos: missing the leading `@`, extra dashes or spaces between "to" and
 * "do", and mismatched case.
 *
 * @see https://www.drupal.org/node/1354
 */
final class TodoCommentRule implements Rule
{
    /**
     * Matches "to-do" wording that is not already a correctly formed
     * `@todo Some text.` tag.
     */
    private const PATTERN = '/(?x)
        ^(\/|\s)*
        (?i)
        (?=(
          @+to(-|\s|)+do
          \h*(-|:)*
          |
          to(-)*do
          (\s-|:)*
        ))
        (?-i)
        (?!
          @todo\s
          (?!-|:)\S
        )/m';

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/todo-comment',
            name: 'To-do comment format',
            description: 'Reports a to-do comment that does not follow the "@todo Fix problem X here." format.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // PATTERN only fires on some "to…do" run with dash or space
        // separators, so a file without one anywhere cannot match. The
        // loose scan is a superset of PATTERN's wording variants.
        $this->gate ??= new FileGate(pattern: '/to[-\s]*do/i');
        if (!$this->gate->passes($context->file)) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind === TriviaKind::DocBlockComment) {
                foreach (Docblocks::lines($context->file, $trivia->span) as $line) {
                    $this->check($context, $line->text, $line->offset);
                }

                continue;
            }

            $this->check($context, $context->file->getText($trivia->span), $trivia->span->start);
        }
    }

    private function check(LintContext $context, string $text, int $offset): void
    {
        if (preg_match(self::PATTERN, $text) !== 1) {
            return;
        }

        $context->report(Issue::new(
            'To-do comments must use the format "@todo Fix problem X here."',
            new Span($offset, $offset + strlen($text)),
        )->withLink('https://www.drupal.org/node/1354'));
    }
}
