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
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function array_slice;
use function preg_match;
use function rtrim;
use function str_replace;
use function strlen;
use function strspn;
use function substr;
use function substr_count;
use function trim;

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

    /**
     * The wordings of a core deprecation that phpcbf rewrites, copied from
     * Coder. It reads the versions from groups 5 and 12 and the rest of the
     * text from group 14. `[ |from|before|in|the]` is a character class in
     * Coder too.
     */
    private const FIXABLE = '/^(.*)(as of|in) (drupal|)( |:|)+([\d\.\-xdev\?]+)(,| |. |)(.*)(removed|removal)([ |from|before|in|the]*) (drupal|)( |:|)([\d\-\.xdev]+)( |,|$)+(?:release|)(?:[\.,])*(.*)$/i';

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
            $issue = Issue::new(
                'The @deprecated text does not match the standard format: ' . self::FORMAT,
                $tag->contentSpan(),
            );
            $edit = self::layoutEdit($tag);
            $context->report($edit === null ? $issue : $issue->withEdit($edit));

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

    /**
     * Rewrites the first line of a core deprecation into the standard
     * wording, as phpcbf does, or returns null when the line does not read
     * like one.
     *
     * The edit drops the text before "in" or "as of", and the text between
     * the two versions. It writes `drupal:` before each version, turns `x`
     * into `0`, drops `-dev` and pads the version to three parts. The lines
     * below the first stay. The edit is potentially unsafe, because it
     * changes the wording and can drop words that matter.
     */
    private static function layoutEdit(DocblockTag $tag): ?TextEdit
    {
        // The first line with text, if it follows the tag line with no blank
        // line between, as Coder collects the text.
        $line = $tag->lines[0];
        if (trim($line->text) === '') {
            $line = $tag->lines[1] ?? null;
            if ($line === null || trim($line->text) === '') {
                return null;
            }
        }

        $indent = strspn($line->text, characters: " \t");
        $text = substr($line->text, $indent);
        $matches = [];
        if (preg_match(self::FIXABLE, $text, $matches) !== 1) {
            return null;
        }

        $corrected = trim(
            'in drupal:'
                . self::paddedVersion($matches[5])
                . ' and is removed from drupal:'
                . self::paddedVersion($matches[12])
                . '. '
                . trim($matches[14]),
        );

        $start = $line->offset + $indent;

        return TextEdit::replace(new Span($start, $start + strlen($text)), $corrected)->withSafety(
            Safety::PotentiallyUnsafe,
        );
    }

    /**
     * Writes a version the way Coder's fix does: `x` becomes `0`, `-dev`
     * goes, and `.0` parts are added up to three parts.
     */
    private static function paddedVersion(string $version): string
    {
        $version = str_replace(['-dev', 'x'], ['', '0'], trim($version, characters: '.'));
        while (substr_count($version, needle: '.') < 2) {
            $version .= '.0';
        }

        return $version;
    }
}
