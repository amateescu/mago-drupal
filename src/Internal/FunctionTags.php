<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Span;

/**
 * The tags of a function's or method's docblock, for the rules that check
 * them.
 *
 * Coder checks no tags of a function with no docblock, with a comment in
 * the wrong style, or with a docblock tagged `@file`, which belongs to the
 * file. Those get no tags here. The result is kept for the last lint
 * context, as in `DeclarationComment`, so the tags are read once per
 * function.
 *
 * @internal
 */
final class FunctionTags
{
    private static ?LintContext $context = null;

    private static ?self $last = null;

    /**
     * @var null|list<array{DocblockTag, ?ParamTag}>
     */
    private ?array $params = null;

    private ?ReturnTag $return = null;

    private bool $returnRead = false;

    /**
     * @param list<DocblockTag> $tags
     */
    private function __construct(
        public readonly Span $docblock,
        public readonly array $tags,
    ) {}

    public static function of(LintContext $context): ?self
    {
        if (self::$context === $context) {
            return self::$last;
        }

        $comment = DeclarationComment::of($context);
        $docblock = $comment->docblock();
        self::$context = $context;

        return self::$last = $docblock === null || $comment->hasFileTag($context->file)
            ? null
            : new self($docblock, Docblocks::tags($context->file, $docblock));
    }

    /**
     * The tags with a name, in order.
     *
     * @return list<DocblockTag>
     */
    public function named(string $name): array
    {
        $named = [];
        foreach ($this->tags as $tag) {
            if ($tag->name !== $name) {
                continue;
            }

            $named[] = $tag;
        }

        return $named;
    }

    /**
     * Each `@param` tag, with its reading, or null when it has no variable.
     *
     * @return list<array{DocblockTag, ?ParamTag}>
     */
    public function params(): array
    {
        if ($this->params === null) {
            $this->params = [];
            foreach ($this->named('param') as $tag) {
                $this->params[] = [$tag, ParamTag::read($tag)];
            }
        }

        return $this->params;
    }

    /**
     * The first `@return` tag, or null when there is none.
     */
    public function returnTag(): ?ReturnTag
    {
        if (!$this->returnRead) {
            $this->returnRead = true;
            $this->return = ReturnTag::read($this->named('return'));
        }

        return $this->return;
    }
}
