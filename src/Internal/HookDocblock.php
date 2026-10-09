<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal;

use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;
use Mago\Sdk\Syntax\TriviaKind;

use function count;
use function preg_match;
use function strlen;
use function strncasecmp;
use function substr;
use function trim;

/**
 * Reads the docblock of a function the way Coder's hook comment sniff does.
 *
 * @internal
 */
final class HookDocblock
{
    private function __construct() {}

    /**
     * The docblock that touches a function's `function` keyword, with only
     * whitespace between. An attribute or a comment between the two means
     * the docblock belongs to something else.
     */
    public static function above(SourceFile $file, Node $function): ?Span
    {
        // The function's span starts at its attributes, so the lookup starts
        // at the keyword.
        foreach ($file->getChildren($function) as $child) {
            if ($child->kind !== NodeKind::Keyword) {
                continue;
            }

            $anchor = new Node($function->id, $function->kind, $child->span, $function->parentId);
            $closest = Docblocks::closest($file, $anchor);

            return $closest !== null && $closest->kind === TriviaKind::DocBlockComment ? $closest->span : null;
        }

        return null;
    }

    /**
     * The lines that Coder takes as the short description: the first line
     * with text, and each line right below it that holds a string. A tag's
     * text counts as a string, so a `@param` right under the first line is
     * part of the description. A blank line or a tag with no text ends it.
     * An empty result means there is no text, or the first line is a tag.
     *
     * @return list<DocblockLine>
     */
    public static function summary(SourceFile $file, Span $docblock): array
    {
        $lines = Docblocks::lines($file, $docblock);
        $count = count($lines);
        $index = 0;
        while ($index < $count && trim($lines[$index]->text) === '') {
            ++$index;
        }

        if ($index === $count || preg_match('/^\s*@[a-zA-Z]/', $lines[$index]->text) === 1) {
            return [];
        }

        $summary = [$lines[$index]];
        for (++$index; $index < $count; ++$index) {
            $string = [];
            if (preg_match('/^\s*(?:@[a-zA-Z][\w-]*\s*)?(\S.*)$/', $lines[$index]->text, $string) !== 1) {
                break;
            }

            $offset = $lines[$index]->offset + strlen($lines[$index]->text) - strlen($string[1]);
            $summary[] = new DocblockLine($string[1], $offset);
        }

        return $summary;
    }

    /**
     * The hook name for a function in a file named after its extension, as
     * in `hook_form_alter` for `my_mod_form_alter` in `my_mod.module`. The
     * function must start with the extension's machine name and an
     * underscore, and have more after it.
     */
    public static function hookName(SourceFile $file, string $function): ?string
    {
        $drupal = DrupalFile::fromSource($file);
        $prefix = $drupal->name . '_';
        if (!$drupal->isProcedural() || strncasecmp($function, $prefix, strlen($prefix)) !== 0) {
            return null;
        }

        $rest = substr($function, strlen($prefix));

        return $rest === '' ? null : 'hook_' . $rest;
    }
}
