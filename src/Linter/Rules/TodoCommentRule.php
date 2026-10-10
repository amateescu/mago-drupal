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
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function explode;
use function preg_match;
use function strlen;
use function strspn;
use function substr;

use const PREG_OFFSET_CAPTURE;

/**
 * Reports a to-do comment that does not start with `@todo`.
 *
 * Ports Drupal.Commenting.TodoCommentSniff. Drupal's release tooling searches
 * for open to-dos and misses the other spellings. The rule reports those
 * spellings: a missing leading `@`, extra dashes or spaces between "to" and
 * "do", and a different case.
 *
 * The fix writes `@todo ` in place of the spelling and the dashes, colons
 * and spaces after it, as phpcbf does. It skips a to-do with no text after
 * it, and a word that only starts with "todo", such as "todos".
 *
 * @see https://www.drupal.org/node/1354
 */
final class TodoCommentRule implements Rule
{
    /**
     * Matches a "to-do" spelling that is not a correctly formed
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
        // PATTERN matches only a "to…do" run with dash or space separators,
        // so a file without one cannot match. The loose search is a superset
        // of the spellings that PATTERN matches.
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

            // Coder reads each line of a `/* */` comment as its own token,
            // so every line gets its own check, issue and fix.
            $offset = $trivia->span->start;
            foreach (explode("\n", $context->file->getText($trivia->span)) as $line) {
                $this->check($context, $line, $offset);
                $offset += strlen($line) + 1;
            }
        }
    }

    private function check(LintContext $context, string $text, int $offset): void
    {
        $matches = [];
        if (preg_match(self::PATTERN, $text, $matches, flags: PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $issue = Issue::new(
            'Write a to-do comment in the format "@todo Fix problem X here."',
            new Span($offset, $offset + strlen($text)),
        )->withLink('https://www.drupal.org/node/1354');

        // Group 2 is the spelling with the dashes and colons after it, as a
        // value and byte offset pair. The stub for preg_match() does not
        // model the offset-capture shape.
        // @mago-expect analysis:docblock-type-mismatch
        /** @var array{string, int} $group */
        $group = $matches[2];
        [$spelling, $start] = $group;
        $end = $start + strlen($spelling);
        $end += strspn($text, characters: " \t", offset: $end);
        if (
            preg_match('/^@*to[-\s]*do\w/i', substr($text, $start)) === 1
            || preg_match('/\G(?!\*\/)\S/', $text, offset: $end) !== 1
        ) {
            $context->report($issue);

            return;
        }

        $context->report($issue->withEdit(TextEdit::replace(
            new Span($offset + $start, $offset + $end),
            text: '@todo ',
        )));
    }
}
