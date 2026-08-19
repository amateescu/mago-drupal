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
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function preg_match;

/**
 * Checks the wording of a `@deprecated` docblock tag and the `@see` tag
 * required right after it.
 *
 * Ports Drupal.Commenting.Deprecated. A separate grammar from
 * `drupal/deprecation-message`, which checks `trigger_error()`'s message
 * text: that sentence embeds its change-record link with "… See %link%",
 * while `@deprecated` writes the link as a following `@see` tag instead.
 *
 * @see https://www.drupal.org/node/2807731
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
        // Every match writes the literal tag, so a substring scan can skip
        // the docblock parsing for the whole file.
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

                $this->checkTag($context, $tag, $tags[$index + 1] ?? null);
            }
        }
    }

    private function checkTag(LintContext $context, DocblockTag $tag, ?DocblockTag $next): void
    {
        $this->checkLayout($context, $tag);

        if ($next === null || $next->name !== 'see') {
            $context->report(Issue::new(
                'Each @deprecated tag must have a @see tag immediately following it.',
                $tag->nameSpan,
            ));

            return;
        }

        $linkProblem = DeprecationMessage::linkProblem($next->content());
        if ($linkProblem !== null) {
            $context->report(Issue::new($linkProblem, $next->contentSpan()));
        }
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
