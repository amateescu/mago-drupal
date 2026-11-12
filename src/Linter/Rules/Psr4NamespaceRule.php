<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Drupal\Psr4Namespace;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function count;
use function ltrim;
use function str_ends_with;

/**
 * Reports a namespace that doesn't match the path of its file under `src/`
 * or `tests/src/`.
 *
 * Drupal registers `Drupal\{module}` for the `src/` directory of a module,
 * theme or profile, and `Drupal\Tests\{module}` for `tests/src/`. A class in
 * another namespace isn't found by the autoloader. The extension is the one
 * of the nearest `*.info.yml` file. Core's `lib/Drupal/Core`,
 * `lib/Drupal/Component` and `tests/Drupal/*Tests` directories map the same
 * way.
 *
 * A file with several namespaces isn't reported: a test declares a second
 * one to replace a function of the code it tests. A file that doesn't
 * declare a class, interface, trait or enum isn't loaded by name. Coder
 * doesn't have such a sniff.
 *
 * @internal
 */
final class Psr4NamespaceRule implements Rule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/psr4-namespace',
            name: 'PSR-4 namespace',
            description: "Reports a namespace that doesn't match the path of its file under src/ or tests/src/.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // The file is read once, from its only namespace.
            targets: [NodeKind::Namespace],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;
        if (!str_ends_with($file->path, '.php') || count($file->getNodes(NodeKind::Namespace)) !== 1) {
            return;
        }

        $expected = Psr4Namespace::of($file->path);
        $name = $expected === null ? null : self::name($file, $context->node);
        if ($expected === null || $name === null) {
            return;
        }

        $declared = ltrim($file->getText($name), characters: '\\');
        if ($declared === $expected) {
            return;
        }

        $context->report(Issue::new(
            "The namespace must be {$expected} to match the path of the file. The autoloader doesn't find a class in {$declared} here.",
            $name->span,
        ));
    }

    /**
     * The name of a namespace that declares a class, interface, trait or
     * enum, or null.
     */
    private static function name(SourceFile $file, Node $namespace): ?Node
    {
        foreach ([NodeKind::Class_, NodeKind::Interface, NodeKind::Trait, NodeKind::Enum] as $kind) {
            if ($file->getFirstDescendant($namespace, $kind) === null) {
                continue;
            }

            foreach ($file->getChildren($namespace) as $child) {
                if ($child->kind === NodeKind::Identifier) {
                    return $child;
                }
            }
        }

        return null;
    }
}
