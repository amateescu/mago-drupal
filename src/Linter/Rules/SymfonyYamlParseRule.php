<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\FileGate;
use amateescu\MagoDrupal\Internal\Nodes;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\NodeKind;

use function strtolower;

/**
 * Reports a direct `Symfony\Component\Yaml\Yaml::parse()` call.
 *
 * Ports phpstan-drupal's SymfonyYamlParseRule. Drupal's own
 * `\Drupal\Component\Serialization\Yaml::decode()` selects the fast parser
 * when it is available and applies Drupal's parser flags.
 */
final class SymfonyYamlParseRule implements Rule
{
    private readonly FileGate $gate;

    public function __construct()
    {
        // Mago dispatches every static call in the file here, so the rule
        // first checks the file text for the call shape.
        $this->gate = new FileGate(pattern: '/::\s*parse\s*\(/i');
    }

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/symfony-yaml-parse',
            name: 'Symfony Yaml::parse()',
            description: 'Reports a Symfony\Component\Yaml\Yaml::parse() call. It bypasses Drupal\Component\Serialization\Yaml::decode().',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::StaticMethodCall],
        );
    }

    public function lint(LintContext $context): void
    {
        if (!$this->gate->passes($context->file)) {
            return;
        }

        $name = Calls::name($context->file, $context->node);
        if ($name === null || strtolower($name) !== 'parse') {
            return;
        }

        $target = $context->file->getChildren($context->node)[0] ?? null;
        $class = $target === null ? null : Nodes::resolved($context->file, $target);
        if ($class === null || strtolower($class) !== 'symfony\component\yaml\yaml') {
            return;
        }

        $context->report(Issue::new(
            'Use \Drupal\Component\Serialization\Yaml::decode() instead of Symfony\'s Yaml::parse().',
            $context->node->span,
        )->withHelp(
            'Drupal\'s wrapper uses the faster parser when it is installed, and it applies Drupal\'s parser flags.',
        ));
    }
}
