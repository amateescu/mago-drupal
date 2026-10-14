<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\Trivia;
use Mago\Sdk\Syntax\TriviaKind;

/**
 * The comment right above a class-like, a function, a method or a property.
 *
 * The rules for a missing docblock, for the comment style and for the tags
 * all read the comment of the same declaration. The worker passes one
 * `LintContext` to every rule that targets a node, so the result for the
 * last context is kept and the lookup runs once per node.
 *
 * @internal
 */
final class DeclarationComment
{
    private static ?LintContext $context = null;

    private static ?self $last = null;

    private function __construct(
        private readonly Node $declaration,
        private readonly Node $anchor,
        public readonly ?Trivia $comment,
    ) {}

    public static function of(LintContext $context): self
    {
        if (self::$last !== null && self::$context === $context) {
            return self::$last;
        }

        // The attributes of a property belong to the plain or hooked property
        // inside the declaration.
        $declaration = $context->node->kind === NodeKind::Property
            ? $context->file->getChildren($context->node)[0] ?? $context->node
            : $context->node;
        $anchor = Docblocks::commentAnchor($context->file, $declaration);
        self::$context = $context;

        return self::$last = new self($declaration, $anchor, Docblocks::closest($context->file, $anchor));
    }

    /**
     * The span of the docblock, or null when there is no comment or the
     * comment is not a docblock.
     */
    public function docblock(): ?Span
    {
        return $this->comment !== null && $this->comment->kind === TriviaKind::DocBlockComment
            ? $this->comment->span
            : null;
    }

    /**
     * Whether the comment is a `//` run or a `/* *\/` comment where the
     * docblock belongs.
     */
    public function hasWrongStyle(): bool
    {
        return $this->comment !== null && $this->comment->kind !== TriviaKind::DocBlockComment;
    }

    /**
     * The edit that turns the comment into a docblock, or null when there is
     * none.
     */
    public function styleEdit(SourceFile $file): ?TextEdit
    {
        return $this->comment === null
            ? null
            : CommentDocblock::edit($file, $this->comment, $this->declaration, $this->anchor);
    }

    /**
     * Whether the docblock has an `@file` tag. Such a docblock belongs to the
     * file, and Coder takes the declaration for undocumented.
     */
    public function hasFileTag(SourceFile $file): bool
    {
        $docblock = $this->docblock();
        if ($docblock === null) {
            return false;
        }

        foreach (Docblocks::tags($file, $docblock) as $tag) {
            if ($tag->name === 'file') {
                return true;
            }
        }

        return false;
    }
}
