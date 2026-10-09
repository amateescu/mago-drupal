<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DrupalFile;
use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function preg_replace;
use function str_starts_with;
use function strcasecmp;
use function strlen;
use function strspn;
use function strtolower;
use function substr;
use function trim;

/**
 * Reports a function whose docblock says `Implements hook_form_alter().`
 * but whose name is not the module name and `_form_alter`.
 *
 * Ports DrupalPractice.FunctionDefinitions.FormAlterDoc. The usual cause is a
 * `hook_form_FORM_ID_alter()` implementation with the wrong docblock.
 */
final class FormAlterCommentRule implements Rule
{
    private const HOOK_LINE = 'Implements hook_form_alter().';

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/form-alter-comment',
            name: 'Form alter comment',
            description: 'Reports a function documented as hook_form_alter() that is not named after the module.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // Almost no file holds the hook line. A text search is cheaper than
        // the checks below.
        $this->gate ??= new FileGate(needles: [self::HOOK_LINE]);
        if (!$this->gate->passes($file)) {
            return;
        }

        $module = DrupalFile::fromSource($file);
        if (!$module->isNamedByFile()) {
            return;
        }

        $identifier = $this->nameRightAfterKeyword($file, $context->node);
        if ($identifier === null) {
            return;
        }

        $docblock = Docblocks::attachedTo($file, $context->node);
        if ($docblock === null) {
            return;
        }

        $hook = $this->hookLine($file, $docblock);
        if ($hook === null) {
            return;
        }

        // PHP ignores the case of a function name, so a name in other case
        // still implements the hook.
        $name = $file->getText($identifier);
        $expected = $module->name . '_form_alter';
        if (strcasecmp($name, $expected) === 0) {
            return;
        }

        $context->report(Issue::new(
            "Doc comment indicates hook_form_alter() but function signature is \"{$name}\" instead of \"{$expected}\". Did you mean hook_form_FORM_ID_alter()?",
            $hook,
        ));
    }

    /**
     * Returns the name of a function that starts with the `function`
     * keyword, or NULL.
     *
     * A modifier, an attribute or a `&` before the name breaks the link
     * between the docblock and the name, as in Coder. Comments between the
     * keyword and the name are fine.
     */
    private function nameRightAfterKeyword(SourceFile $file, Node $function): ?Node
    {
        $keyword = $file->getChildren($function)[0] ?? null;
        if (
            $keyword === null
            || $keyword->kind !== NodeKind::Keyword
            || strtolower($file->getText($keyword)) !== 'function'
        ) {
            return null;
        }

        $identifier = Nodes::declaredIdentifier($file, $function);
        if ($identifier === null) {
            return null;
        }

        $between = substr($file->contents, $keyword->span->end, $identifier->span->start - $keyword->span->end);
        $stripped = preg_replace('~/\*.*?\*/|(?://|#)[^\n]*~s', replacement: '', subject: $between);

        return trim((string) $stripped) === '' ? $identifier : null;
    }

    /**
     * Returns the span of the hook text in the first docblock line that
     * starts with it.
     */
    private function hookLine(SourceFile $file, Span $docblock): ?Span
    {
        foreach (Docblocks::lines($file, $docblock) as $line) {
            $indent = strspn($line->text, characters: " \t");
            if (str_starts_with(substr($line->text, $indent), self::HOOK_LINE)) {
                $start = $line->offset + $indent;

                return new Span($start, $start + strlen(self::HOOK_LINE));
            }
        }

        return null;
    }
}
