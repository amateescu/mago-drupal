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
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\TriviaKind;

use function ltrim;
use function preg_match;
use function str_contains;
use function substr;

/**
 * Checks the style and word order of an inline `@var` type declaration.
 *
 * Ports Drupal.Commenting.InlineVariableComment. A `//` or `#` comment
 * containing `@var` should use `/** *\/` delimiters instead, unless it sits
 * right before a declaration, where it is really that declaration's own
 * comment written in the wrong style, a problem `drupal/class-comment`,
 * `drupal/file-comment` and `drupal/variable-comment` already report. A real
 * `@var` docblock tag must write the type before the variable name.
 */
final class InlineVariableCommentRule implements Rule
{
    private const DECLARATION_KEYWORDS = '/^(class|interface|trait|enum|function|public|private|protected|final|static|abstract|const|var|include|require)\b/';

    private const LOOKAHEAD = 40;

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/inline-variable-comment',
            name: 'Inline variable comment',
            description: 'Checks the style and word order of an inline @var type declaration.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Both branches key on the literal tag, so a file without "@var"
        // anywhere cannot match.
        $this->gate ??= new FileGate(needles: ['@var']);
        if (!$this->gate->passes($context->file)) {
            return;
        }

        foreach ($context->file->getTrivia() as $trivia) {
            if ($trivia->kind === TriviaKind::DocBlockComment) {
                foreach (Docblocks::tags($context->file, $trivia->span) as $tag) {
                    if ($tag->name !== 'var' || preg_match('/^\$/', $tag->content()) !== 1) {
                        continue;
                    }

                    $context->report(Issue::new(
                        'The variable name should be defined after the type in a @var tag.',
                        $tag->contentSpan(),
                    ));
                }

                continue;
            }

            if (
                str_contains($context->file->getText($trivia->span), '@var')
                && !$this->precedesADeclaration($context->file->contents, $trivia->span->end)
            ) {
                $context->report(Issue::new(
                    'An inline @var declaration should use "/** */" delimiters.',
                    $trivia->span,
                )->withHelp(
                    'Move the @var declaration into its own docblock, or drop the tag if it just repeats a native type hint.',
                ));
            }
        }
    }

    private function precedesADeclaration(string $contents, int $offset): bool
    {
        return preg_match(self::DECLARATION_KEYWORDS, ltrim(substr($contents, $offset, self::LOOKAHEAD))) === 1;
    }
}
