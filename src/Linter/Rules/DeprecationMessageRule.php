<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\DeprecationMessage;
use amateescu\MagoDrupal\Internal\DeprecationStandard;
use amateescu\MagoDrupal\Internal\DeprecationText;
use amateescu\MagoDrupal\Internal\Docblocks;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function in_array;
use function ltrim;
use function str_contains;
use function stripos;
use function strtoupper;
use function trim;

/**
 * Checks the wording of deprecation messages passed to trigger_error().
 *
 * Ports Drupal.Semantics.FunctionTriggerError. Drupal's release tooling parses
 * these strings to build the deprecation report, so the wording has to match.
 *
 * Targets the whole file rather than calls or declarations, so one pass finds
 * every trigger_error(), including the deprecated-file pattern at file scope.
 * The wording standard comes from the enclosing declaration's docblock, or
 * for a file-scope notice from the next file-level docblock, which is how the
 * ported sniff reads it. The scope classification needs its branches, so the
 * complexity is deliberate.
 *
 * @see https://www.drupal.org/node/2856820
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class DeprecationMessageRule implements Rule
{
    private const LINK = 'https://www.drupal.org/node/2856820';

    private const DECLARATION_KINDS = [NodeKind::Function, NodeKind::Method];

    /**
     * Kinds whose span encloses trivia that is not at file level.
     */
    private const SCOPE_KINDS = [
        NodeKind::Function,
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/deprecation-message',
            name: 'Deprecation message format',
            description: 'Checks that E_USER_DEPRECATED messages follow the documented deprecation grammar.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        // Walking the tree costs a lot more than scanning the source, and
        // almost no file contains a deprecation. PHP function names are
        // case-insensitive, so the scan is too.
        if (stripos($context->file->contents, needle: 'trigger_error') === false) {
            return;
        }

        $standards = [];
        $calls = Calls::findFunctions($context->file, $context->node, ['trigger_error'])['trigger_error'] ?? [];
        foreach ($calls as $call) {
            $declaration = $this->enclosingDeclaration($context->file, $call);

            // A declaration's wording standard covers every call in it, so it
            // resolves once per declaration. File-scope notices resolve per
            // call, because their docblock lookup starts at the call.
            $standard = $declaration === null
                ? $this->fileScopeStandard($context->file, $call)
                : ($standards[$declaration->id] ??= $this->declarationStandard($context->file, $declaration));

            $this->check($context, $call, $standard);
        }
    }

    /**
     * Checks one trigger_error() call.
     */
    private function check(LintContext $context, Node $call, DeprecationStandard $standard): void
    {
        // The finder already matched the callee, so the arguments are read
        // straight off the call view.
        $arguments = Calls::positionalArguments($context->file, CallExpression::fromNode($context->file, $call));

        // The constant may be written fully qualified, so the leading
        // backslash comes off before comparing.
        $level = $arguments[1] ?? null;
        if (
            $level === null
            || strtoupper(ltrim(trim($context->file->getText($level)), characters: '\\')) !== 'E_USER_DEPRECATED'
        ) {
            return;
        }

        $message = $arguments[0] ?? null;
        if ($message === null) {
            return;
        }

        $text = DeprecationText::fromNode($context->file, $message);
        if ($text === '') {
            return;
        }

        foreach (DeprecationMessage::problems($text, $standard) as $problem) {
            $context->report(Issue::new($problem, $message->span)->withHelp(
                "The message reads: '{$text}'",
            )->withLink(self::LINK));
        }
    }

    /**
     * Returns the function or method declaration enclosing a call, if any.
     */
    private function enclosingDeclaration(SourceFile $file, Node $call): ?Node
    {
        $parent = $file->getParent($call);
        while ($parent !== null) {
            if (in_array($parent->kind, self::DECLARATION_KINDS, strict: true)) {
                return $parent;
            }

            $parent = $file->getParent($parent);
        }

        return null;
    }

    /**
     * Returns the wording standard for a call inside a declaration.
     *
     * A tagged deprecation must use the strict wording, which fixes the
     * removal phrasing and not just the versions.
     */
    private function declarationStandard(SourceFile $file, Node $declaration): DeprecationStandard
    {
        $docblock = Docblocks::attachedTo($file, $declaration);

        return $docblock !== null && str_contains($file->getText($docblock), '@deprecated')
            ? DeprecationStandard::Strict
            : DeprecationStandard::Relaxed;
    }

    /**
     * Returns the wording standard for a file-scope call.
     *
     * A deprecated-file notice comes before the declarations it covers, so
     * the sniff this ports reads the next file-level docblock after the call.
     */
    private function fileScopeStandard(SourceFile $file, Node $call): DeprecationStandard
    {
        foreach ($file->getTrivia() as $trivia) {
            if ($trivia->kind !== TriviaKind::DocBlockComment || $trivia->span->start < $call->span->end) {
                continue;
            }

            if (!$this->atFileLevel($file, $trivia->span)) {
                continue;
            }

            return str_contains($file->getText($trivia->span), '@deprecated')
                ? DeprecationStandard::Strict
                : DeprecationStandard::Relaxed;
        }

        return DeprecationStandard::Relaxed;
    }

    /**
     * Whether a span sits outside every declaration body.
     */
    private function atFileLevel(SourceFile $file, Span $span): bool
    {
        foreach (self::SCOPE_KINDS as $kind) {
            foreach ($file->getNodes($kind) as $node) {
                if ($node->span->start <= $span->start && $span->end <= $node->span->end) {
                    return false;
                }
            }
        }

        return true;
    }
}
