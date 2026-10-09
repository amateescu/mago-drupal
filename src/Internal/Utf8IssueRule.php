<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\SourceLocation;

use function array_slice;
use function implode;

/**
 * Makes the text of a rule's issues valid UTF-8.
 *
 * Mago stops the whole lint run when an issue's message, notes, help, link
 * or annotation messages are not valid UTF-8. Messages quote names from the
 * source, and a source file can hold any bytes, such as a Latin-1 function
 * name. Each invalid byte becomes `\xHH`, the way Mago prints one. Edits
 * stay as they are, since Mago takes their text as bytes.
 *
 * @internal
 */
final class Utf8IssueRule implements Rule
{
    public function __construct(
        private readonly Rule $rule,
    ) {}

    public function getDefinition(): RuleDefinition
    {
        return $this->rule->getDefinition();
    }

    /**
     * The worker empties the context's issues after each rule, so the list
     * holds this rule's issues only.
     */
    public function lint(LintContext $context): void
    {
        $this->rule->lint($context);
        foreach ($context->issues as $index => $issue) {
            if (Utf8::isValid(self::texts($issue))) {
                continue;
            }

            $context->issues[$index] = self::rebuild($issue);
        }
    }

    /**
     * All of an issue's text, joined so one check covers it.
     */
    private static function texts(Issue $issue): string
    {
        $texts = [$issue->message, ...$issue->notes, $issue->help ?? '', $issue->link ?? ''];
        foreach ($issue->annotations as $annotation) {
            $texts[] = $annotation->message ?? '';
        }

        return implode(separator: "\n", array: $texts);
    }

    /**
     * Builds the issue again from its parts, with every text made valid.
     *
     * The issue's first annotation is its primary one, since `Issue` adds
     * only secondary annotations after it.
     */
    private static function rebuild(Issue $issue): Issue
    {
        $primary = $issue->annotations[0];
        $label = Utf8::optional($primary->message);
        $rebuilt = $primary->file === null
            ? Issue::new(Utf8::valid($issue->message), $primary->span, $label)
            : Issue::at(Utf8::valid($issue->message), new SourceLocation($primary->file, $primary->span), $label);

        foreach ($issue->notes as $note) {
            $rebuilt = $rebuilt->withNote(Utf8::valid($note));
        }

        if ($issue->help !== null) {
            $rebuilt = $rebuilt->withHelp(Utf8::valid($issue->help));
        }

        if ($issue->link !== null) {
            $rebuilt = $rebuilt->withLink(Utf8::valid($issue->link));
        }

        foreach (array_slice($issue->annotations, offset: 1) as $annotation) {
            $label = Utf8::optional($annotation->message);
            $rebuilt = $annotation->file === null
                ? $rebuilt->withSecondaryAnnotation($annotation->span, $label)
                : $rebuilt->withSecondaryLocation(new SourceLocation($annotation->file, $annotation->span), $label);
        }

        foreach ($issue->edits as $edit) {
            $rebuilt = $rebuilt->withEdit($edit);
        }

        return $rebuilt;
    }
}
