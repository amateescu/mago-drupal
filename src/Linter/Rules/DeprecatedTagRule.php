<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\DeprecationMessage;
use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
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

use function array_slice;
use function preg_match;
use function rtrim;
use function strlen;
use function strspn;
use function substr;

/**
 * Checks the wording of a `@deprecated` docblock tag and the `@see` tag
 * that must follow it.
 *
 * Ports Drupal.Commenting.Deprecated. The `drupal/deprecation-message` rule
 * checks the `trigger_error()` message text with a different grammar. That
 * message embeds its change-record link with "… See %link%". A `@deprecated`
 * tag writes the link as a `@see` tag after it instead.
 *
 * @see https://www.drupal.org/node/2807731
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DeprecatedTagRule implements Rule
{
    private const LAYOUT = '/^in (.+) and is removed from (?U)(.+)(?:\. | |\.$|$)(.*)$/';

    private const FORMAT = '@deprecated in %deprecation-version% and is removed from %removal-version%. %extra-info%.';

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/deprecated-tag',
            name: 'Deprecated tag',
            description: 'Checks the wording of a @deprecated docblock tag and the @see tag that must follow it.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Every match holds the literal tag, so a substring scan can skip the
        // docblock parsing for the whole file.
        $this->gate ??= new FileGate(needles: ['@deprecated']);
        if (!$this->gate->passes($context->file)) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment) {
                continue;
            }

            $tags = Docblocks::tags($context->file, $trivia->span);
            foreach ($tags as $index => $tag) {
                if ($tag->name !== 'deprecated') {
                    continue;
                }

                $this->checkTag($context, $tag, self::seeTagAfter($tags, $index));
            }
        }
    }

    /**
     * Returns the first `@see` tag after position $index.
     *
     * The ported sniff accepts any later `@see` in the block. An example or
     * another tag may sit between the two.
     *
     * @param list<DocblockTag> $tags
     */
    private static function seeTagAfter(array $tags, int $index): ?DocblockTag
    {
        foreach (array_slice($tags, $index + 1) as $candidate) {
            if ($candidate->name === 'see') {
                return $candidate;
            }
        }

        return null;
    }

    private function checkTag(LintContext $context, DocblockTag $tag, ?DocblockTag $see): void
    {
        $this->checkLayout($context, $tag);

        if ($see === null) {
            $context->report(Issue::new('Put a @see tag after each @deprecated tag.', $tag->nameSpan));

            return;
        }

        // The url is the tag's first line, which Coder reads too. Lines
        // below it hold other text.
        $line = $see->lines[0];
        $indent = strspn($line->text, characters: " \t");
        $link = rtrim(substr($line->text, $indent));
        if ($link === '') {
            $link = $see->content();
        }

        $linkProblem = DeprecationMessage::linkProblem($link);
        if ($linkProblem === null) {
            return;
        }

        $issue = Issue::new($linkProblem, $see->contentSpan());
        $trailing = $link === rtrim(substr($line->text, $indent)) ? DeprecationMessage::trailingPunctuation($link) : 0;
        if ($trailing > 0) {
            $end = $line->offset + $indent + strlen($link);
            $issue = $issue->withEdit(TextEdit::delete(new Span($end - $trailing, $end)));
        }

        $context->report($issue);
    }

    private function checkLayout(LintContext $context, DocblockTag $tag): void
    {
        $matches = [];
        if (preg_match(self::LAYOUT, $tag->content(), $matches) !== 1) {
            $context->report(Issue::new(
                'The @deprecated text does not match the standard format: ' . self::FORMAT,
                $tag->contentSpan(),
            ));

            return;
        }

        $deprecationProblem = DeprecationMessage::versionProblem('deprecation version', $matches[1]);
        if ($deprecationProblem !== null) {
            $context->report(Issue::new($deprecationProblem, $tag->contentSpan()));
        }

        $removalProblem = DeprecationMessage::versionProblem('removal version', $matches[2]);
        if ($removalProblem !== null) {
            $context->report(Issue::new($removalProblem, $tag->contentSpan()));
        }

        if ($matches[3] === '') {
            $context->report(Issue::new(
                'The @deprecated tag must have %extra-info%. The standard format is: ' . self::FORMAT,
                $tag->contentSpan(),
            ));
        }
    }
}
