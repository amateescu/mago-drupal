<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\FileGate;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function array_key_exists;

/**
 * Reports a use of `$_GET`, `$_POST`, `$_COOKIE` or `$_FILES`.
 *
 * Ports DrupalPractice.Variables.GetRequestData. Code that reads the
 * superglobal cannot run on a request other than the current one, and a test
 * cannot replace it. `$_REQUEST` is left to Mago's `no-request-variable`.
 */
final class RequestSuperglobalRule implements Rule
{
    /**
     * Each superglobal and the property of the request that holds the same
     * data.
     */
    private const PROPERTIES = [
        '$_GET' => 'query',
        '$_POST' => 'request',
        '$_FILES' => 'files',
        '$_COOKIE' => 'cookies',
    ];

    private ?FileGate $gate = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/request-superglobal',
            name: 'Request superglobal',
            description: 'Reports a use of $_GET, $_POST, $_COOKIE or $_FILES. The request stack gives the same data.',
            defaultLevel: Level::Error,
            defaultEnabled: true,
            // Program has the parent chain of each variable in the snapshot.
            targets: [NodeKind::Program],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // Almost no file has one of the four names. A text search is much
        // cheaper than a read of every variable.
        $this->gate ??= new FileGate(needles: ['$_GET', '$_POST', '$_FILES', '$_COOKIE']);
        if (!$this->gate->passes($file)) {
            return;
        }

        foreach ($file->getNodes(NodeKind::DirectVariable) as $variable) {
            $name = $file->getText($variable);
            if (!array_key_exists($name, self::PROPERTIES)) {
                continue;
            }

            $property = self::PROPERTIES[$name];
            $key = $this->indexedKey($file, $variable);
            $use = $key === null ? $property : "{$property}->get({$key})";

            $context->report(Issue::new(
                "Do not read {$name} directly. Inject the request_stack service and use \$stack->getCurrentRequest()->{$use}.",
                $variable->span,
            ));
        }
    }

    /**
     * Returns the source text of the first key that indexes the variable, or
     * NULL when no key does, as in `$_GET`, `$_GET[]` and `foo($_GET)`.
     */
    private function indexedKey(SourceFile $file, Node $variable): ?string
    {
        $wrapper = $file->getParent($variable);
        $expression = $wrapper === null || $wrapper->kind !== NodeKind::Variable ? null : $file->getParent($wrapper);
        $access =
            $expression === null || $expression->kind !== NodeKind::Expression ? null : $file->getParent($expression);
        if ($access === null || $access->kind !== NodeKind::ArrayAccess) {
            return null;
        }

        // The variable is the array of the access, and not its key.
        $parts = $file->getChildren($access);
        if (($parts[0] ?? null)?->id !== $expression?->id || !array_key_exists(1, $parts)) {
            return null;
        }

        return $file->getText($parts[1]);
    }
}
