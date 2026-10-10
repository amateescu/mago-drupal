<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Nodes;
use amateescu\MagoDrupal\Internal\TopLevel;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function preg_match;
use function str_contains;
use function str_starts_with;
use function substr;

/**
 * Reports class properties that are not lowerCamelCase.
 *
 * Ports Drupal.NamingConventions.ValidVariableName.LowerCamelName and
 * PSR2.Classes.PropertyDeclaration.Underscore.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class PropertyNameRule implements Rule
{
    /**
     * Class-like kinds.
     */
    private const CLASS_LIKE = [
        NodeKind::Class_,
        NodeKind::Interface,
        NodeKind::Trait,
        NodeKind::Enum,
        NodeKind::AnonymousClass,
    ];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/property-name',
            name: 'Property name',
            description: 'Reports class properties that do not use lowerCamelCase.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // Program gives the walk up from each property a parent chain.
            // Property puts the properties in the target list.
            targets: [NodeKind::Program, NodeKind::Property],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $file = $context->file;
        foreach ($file->getTargetNodes() as $property) {
            if ($property->kind !== NodeKind::Property) {
                continue;
            }

            $exempt = $this->allowsAnyCase($file, $property);
            // Only the declared items are property names. A hooked property
            // keeps its get and set bodies in the same subtree. A walk of
            // every descendant reports the local variables inside them. A
            // default value can be a large array, and a walk of it is not
            // worth the time. The items are one level under the plain or
            // hooked wrapper.
            foreach ($this->items($file, $property) as $item) {
                $variable = $file->getFirstDescendant($item, NodeKind::DirectVariable);
                if ($variable === null) {
                    continue;
                }

                $name = substr($file->getText($variable), offset: 1);
                if ($name === '' || preg_match('/^[a-z]/', $name) === 1 && !str_contains($name, '_')) {
                    continue;
                }

                // PSR2.Classes.PropertyDeclaration.Underscore has no exempt
                // classes, so a leading underscore is still reported there.
                if ($exempt && !str_starts_with($name, '_')) {
                    continue;
                }

                $context->report(Issue::new(
                    $exempt
                        ? "Do not start the property name \${$name} with an underscore to mark its visibility."
                        : "Write the property \${$name} in lowerCamelCase.",
                    $variable->span,
                ));
            }
        }
    }

    /**
     * The property's declared items, read from the wrapper's children.
     *
     * @return list<Node>
     */
    private function items(SourceFile $file, Node $property): array
    {
        $items = [];
        foreach ($file->getChildren($property) as $child) {
            if ($child->kind === NodeKind::PropertyItem) {
                $items[] = $child;

                continue;
            }

            foreach ($file->getChildren($child) as $grandchild) {
                if ($grandchild->kind !== NodeKind::PropertyItem) {
                    continue;
                }

                $items[] = $grandchild;
            }
        }

        return $items;
    }

    /**
     * Whether Coder allows any case in the property's name: in a config
     * entity or a plugin annotation class.
     */
    private function allowsAnyCase(SourceFile $file, Node $property): bool
    {
        // Coder reads the outermost scope around the property. That is a
        // class only when the class is at the top level of the file. A
        // property of an anonymous class in a method follows the outer class.
        $class = null;
        for ($parent = $file->getParent($property); $parent !== null; $parent = $file->getParent($parent)) {
            if (!in_array($parent->kind, self::CLASS_LIKE, strict: true)) {
                continue;
            }

            $class = $parent;
        }

        if ($class === null || TopLevel::isNested($file, $class)) {
            return false;
        }

        // Coder compares the names as written, so `\Drupal\...\Plugin` or an
        // alias of Plugin does not match. An interface can extend several,
        // and Coder reads the first.
        $parent = Nodes::clauseNames($file, $class, NodeKind::Extends)[0] ?? '';

        return (
            str_contains($parent, 'ConfigEntity')
            || in_array($parent, ['Plugin', 'ViewsPluginAnnotationBase'], strict: true)
            || in_array('AnnotationInterface', Nodes::clauseNames($file, $class, NodeKind::Implements), strict: true)
        );
    }
}
