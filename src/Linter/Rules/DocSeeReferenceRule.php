<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\OuterDocblocks;
use amateescu\MagoDrupal\Internal\SeeTags;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;

/**
 * Checks the `@see` references of the docblocks outside a function body that
 * aren't a function's or a method's.
 *
 * Coder reads the `@see` tags of function and property docblocks only, and
 * `drupal/doc-see-tag` ports those checks. The standard asks the same of every
 * `@see` tag, so this rule checks the references in the other docblocks, such
 * as a class's or a file's.
 */
final class DocSeeReferenceRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/doc-see-reference',
            name: 'Doc see reference',
            description: 'Reports a @see reference with text or punctuation after it, in a docblock that is not a function\'s.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $functionDocblocks = self::functionDocblocks($context->file);
        foreach (OuterDocblocks::of($context->file) as $span) {
            if (array_key_exists($span->start, $functionDocblocks) || OuterDocblocks::isGroup($context->file, $span)) {
                continue;
            }

            SeeTags::checkReferences($context, Docblocks::tags($context->file, $span));
        }
    }

    /**
     * The start offsets of the docblocks of functions and methods.
     *
     * @return array<int, true>
     */
    private static function functionDocblocks(SourceFile $file): array
    {
        $starts = [];
        foreach ([...$file->getNodes(NodeKind::Function), ...$file->getNodes(NodeKind::Method)] as $function) {
            $docblock = Docblocks::attachedTo($file, Docblocks::commentAnchor($file, $function));
            if ($docblock === null) {
                continue;
            }

            $starts[$docblock->start] = true;
        }

        return $starts;
    }
}
