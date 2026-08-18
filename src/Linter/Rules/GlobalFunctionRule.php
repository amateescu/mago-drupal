<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_keys;
use function array_map;
use function implode;
use function preg_match;
use function preg_quote;
use function strtolower;
use function trim;

/**
 * Reports procedural wrappers called from inside a class.
 *
 * Ports DrupalPractice.Objects.GlobalFunction. Object-oriented code should take
 * the service instead, which is what makes it testable.
 *
 * Targets the class rather than the call. A linter snapshot only holds the
 * subtree under the target node, so a rule cannot ask a call what encloses it.
 */
final class GlobalFunctionRule implements Rule
{
    /**
     * Procedural functions mapped to what a class should use instead.
     */
    private const REPLACEMENTS = [
        'drupal_get_destination' => 'the "redirect.destination" service',
        'drupal_render' => 'the "renderer" service',
        'entity_load' => 'the "entity_type.manager" service',
        'file_load' => 'the "entity_type.manager" service',
        'format_date' => 'the "date.formatter" service',
        'node_load' => 'the "entity_type.manager" service',
        'node_load_multiple' => 'the "entity_type.manager" service',
        'node_type_load' => 'the "entity_type.manager" service',
        't' => '$this->t() from StringTranslationTrait',
        'taxonomy_term_load' => 'the "entity_type.manager" service',
        'taxonomy_vocabulary_load' => 'the "entity_type.manager" service',
        'user_load' => 'the "entity_type.manager" service',
        'user_role_load' => 'the "entity_type.manager" service',
    ];

    private const TARGETS = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/global-function',
            name: 'Global function in a class',
            description: 'Reports procedural Drupal functions called from inside a class.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: self::TARGETS,
        );
    }

    public function lint(LintContext $context): void
    {
        // Scanning the source is far cheaper than walking the subtree, and
        // most classes call none of these.
        if (preg_match(self::candidatePattern(), $context->file->getText($context->node)) !== 1) {
            return;
        }

        // One walk over the class, rather than one per method. Walking the
        // tree in PHP is the dominant cost of this rule.
        foreach (Calls::findFunctions(
            $context->file,
            $context->node,
            array_keys(self::REPLACEMENTS),
        ) as $name => $calls) {
            foreach ($calls as $call) {
                // A class nested inside this one is its own target, so its
                // calls are reported there.
                if (Nodes::isNestedInside($context->file, $call, $context->node, self::TARGETS)) {
                    continue;
                }

                // Static methods have no `$this`, so the trait and service
                // replacements have nothing to bind to.
                if ($this->inStaticMethod($context->file, $call)) {
                    continue;
                }

                $this->report($context, $call, $name);
            }
        }
    }

    /**
     * Builds the candidate pre-scan regex from the replacement table.
     *
     * The lookbehind keeps `$this->t()` from counting as a candidate, which
     * is what makes the scan selective.
     */
    private static function candidatePattern(): string
    {
        static $pattern = '';
        if ($pattern === '') {
            $names = array_map(static fn(string $name): string => preg_quote(
                $name,
                delimiter: '/',
            ), array_keys(self::REPLACEMENTS));

            $pattern = '/(?<![>\w$])(' . implode('|', $names) . ')\s*\(/i';
        }

        return $pattern;
    }

    /**
     * Whether the call sits in a static method of the target class.
     */
    private function inStaticMethod(SourceFile $file, Node $call): bool
    {
        $parent = $file->getParent($call);
        while ($parent !== null) {
            if ($parent->kind === NodeKind::Method) {
                return $this->isStatic($file, $parent);
            }

            $parent = $file->getParent($parent);
        }

        return false;
    }

    /**
     * Reports one procedural call, naming the replacement.
     */
    private function report(LintContext $context, Node $call, string $name): void
    {
        $replacement = self::REPLACEMENTS[$name];

        $context->report(Issue::new("Use {$replacement} instead of {$name}().", $call->span)->withHelp(
            'Injected services can be swapped in tests, procedural calls cannot.',
        ));
    }

    /**
     * Whether a method declaration has the static modifier.
     */
    private function isStatic(SourceFile $file, Node $method): bool
    {
        foreach ($file->getChildren($method) as $child) {
            if ($child->kind === NodeKind::Modifier && strtolower(trim($file->getText($child))) === 'static') {
                return true;
            }
        }

        return false;
    }
}
