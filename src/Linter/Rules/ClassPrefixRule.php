<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\InfoFile;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function array_map;
use function explode;
use function implode;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function ucfirst;

/**
 * Reports a class or interface in a non-namespaced file whose name does not
 * start with the module name.
 *
 * Ports DrupalPractice.General.ClassName. A class in the global namespace
 * shares it with the classes of every other module. A file with a
 * namespace is exempt from the first `namespace` statement on.
 */
final class ClassPrefixRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/class-prefix',
            name: 'Class prefix',
            description: 'Reports a class or interface in the global namespace that does not start with the module name.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            // Program makes the Program pass read the target list. The other
            // kinds put the declarations and the namespace in that list.
            targets: [NodeKind::Program, NodeKind::Namespace, NodeKind::Class_, NodeKind::Interface],
        );
    }

    public function lint(LintContext $context): void
    {
        if ($context->node->kind !== NodeKind::Program) {
            return;
        }

        $file = $context->file;
        $module = null;

        // The target list is in source order. Everything after the first
        // namespace statement is in a namespace, a braced one included.
        foreach ($file->getTargetNodes() as $declaration) {
            if ($declaration->kind === NodeKind::Namespace) {
                return;
            }

            if ($declaration->kind !== NodeKind::Class_ && $declaration->kind !== NodeKind::Interface) {
                continue;
            }

            $identifier = Nodes::declaredIdentifier($file, $declaration);
            if ($identifier === null) {
                continue;
            }

            // The name can come from an info file on disk, so it is read
            // only for a file with a class outside a namespace.
            $module ??= InfoFile::moduleName($file->path) ?? '';
            if ($module === '') {
                return;
            }

            // A module name with underscores gives two accepted prefixes.
            // Views classes keep the underscores, and others drop them.
            $declared = strtolower($file->getText($identifier));
            $name = strtolower($module);
            if (
                str_starts_with($declared, str_replace('_', replace: '', subject: $name))
                || str_starts_with($declared, $name)
            ) {
                continue;
            }

            $kind = $declaration->kind === NodeKind::Class_ ? 'class' : 'interface';
            $camel = implode('', array_map(ucfirst(...), explode('_', $module)));
            $suffix = $file->getText($identifier);
            $context->report(Issue::new(
                "The {$kind} name must start with the module name, as in {$camel}{$suffix}.",
                $identifier->span,
            )->withHelp(
                'Classes in the global namespace are shared by every module. The module name keeps them apart.',
            ));
        }
    }
}
