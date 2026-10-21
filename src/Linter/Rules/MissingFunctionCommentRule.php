<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Cache\FileMemo;
use amateescu\MagoDrupal\Internal\Comment\DeclarationComment;
use amateescu\MagoDrupal\Internal\Drupal\CoreFiles;
use amateescu\MagoDrupal\Internal\Syntax\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function preg_match;
use function stripos;
use function strtolower;

/**
 * Checks that a function or method has a docblock.
 *
 * Ports `Missing` of Drupal.Commenting.FunctionComment. A docblock tagged
 * `@file` above a function belongs to the file, so the function counts as
 * undocumented. `drupal/function-comment` reports a comment in the wrong
 * style.
 */
final class MissingFunctionCommentRule implements Rule
{
    /**
     * The include patterns of `Missing` in core's `phpcs.xml.dist`.
     */
    private const CORE_DIRECTORIES =
        '#(?:^|/)core/(?:.*/src/Plugin/|lib/|modules/system/tests/modules/HookOrder/)'
            . '|/(?:Database|FunctionalJavascript|Kernel|KernelTests|Functional|Unit)/#';

    /**
     * @var FileMemo<list<int>>|null
     */
    private ?FileMemo $constructorMentions = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/missing-function-comment',
            name: 'Missing function comment',
            description: 'Reports a function or method with no docblock, a constructor excepted.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!self::checkedOnCore($context->file->path) && CoreFiles::contains($context->file->path)) {
            return;
        }

        $comment = DeclarationComment::of($context);
        if ($comment->comment === null) {
            // A constructor with nothing above it doesn't need a docblock. One
            // with a comment above it is checked like any other method.
            if ($this->isConstructor($context)) {
                return;
            }

            $context->report(Issue::new('The function doesn\'t have a docblock.', $context->node->span));

            return;
        }

        if ($comment->hasFileTag($context->file)) {
            $context->report(Issue::new('The function doesn\'t have a docblock.', $context->node->span));
        }
    }

    /**
     * Whether core's `phpcs.xml.dist` checks the functions of the file at
     * $path for a docblock.
     */
    public static function checkedOnCore(string $path): bool
    {
        return preg_match(self::CORE_DIRECTORIES, $path) === 1;
    }

    /**
     * Whether the node is a method named `__construct`. PHP ignores case in
     * method names, so `__CONSTRUCT` counts too.
     */
    private function isConstructor(LintContext $context): bool
    {
        if ($context->node->kind !== NodeKind::Method) {
            return false;
        }

        // A method whose text does not have the name cannot be the
        // constructor. The rule finds the mentions once for each file text
        // and keeps them in FileMemo. Checking a method against them excludes
        // almost every method without reading its nodes. The name lookup
        // below must read the nodes.
        $contents = $context->file->contents;
        $this->constructorMentions ??= new FileMemo();
        /** @var list<int> $mentions */
        $mentions = $this->constructorMentions->get($contents, static fn(): array => self::mentionOffsets($contents));
        $span = $context->node->span;
        foreach ($mentions as $offset) {
            if ($offset >= $span->start && $offset < $span->end) {
                return strtolower(Nodes::declaredName($context->file, $context->node) ?? '') === '__construct';
            }
        }

        return false;
    }

    /**
     * The byte offsets of every `__construct` in the file, in any case.
     *
     * @return list<int>
     */
    private static function mentionOffsets(string $contents): array
    {
        $offsets = [];
        $offset = stripos($contents, needle: '__construct');
        while ($offset !== false) {
            $offsets[] = $offset;
            $offset = stripos($contents, needle: '__construct', offset: $offset + 1);
        }

        return $offsets;
    }
}
