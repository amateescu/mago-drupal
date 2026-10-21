<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Docblock;

use amateescu\MagoDrupal\Internal\Comment\DeclarationComment;
use Mago\Sdk\Linter\LintContext;

use function preg_match;
use function stripos;

/**
 * The tags of a property's docblock, for the rules that check them.
 *
 * @internal
 */
final class PropertyTags
{
    private function __construct() {}

    /**
     * The tags, or null when the property doesn't have a docblock, a comment in
     * the wrong style, or a docblock with `{@inheritdoc}`. A property that
     * inherits its parent's docblock doesn't have anything of its own to check.
     * Coder's sniff makes the same exemption.
     *
     * @return null|list<DocblockTag>
     */
    public static function of(LintContext $context): ?array
    {
        $docblock = DeclarationComment::of($context)->docblock();
        if ($docblock === null || stripos($context->file->getText($docblock), needle: '{@inheritdoc}') !== false) {
            return null;
        }

        return Docblocks::tags($context->file, $docblock);
    }

    /**
     * The variable name that a `@var` tag's content starts with, or null.
     * `$this` is a type, and Coder accepts it.
     */
    public static function leadingName(string $content): ?string
    {
        $name = [];

        return preg_match(DocType::VARIABLE, $content, $name) === 1 && $name[0] !== '$this' ? $name[0] : null;
    }
}
