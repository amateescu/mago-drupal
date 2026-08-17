<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\NodeKind;

use function count;
use function in_array;
use function strtolower;
use function trim;

/**
 * Reports md5(), sha1() and crc32() in favour of hash() with an xxHash algorithm.
 *
 * Ports the disallowed-call configuration Drupal core carries in
 * phpstan.neon.dist, including the variant that passes a weak algorithm name to
 * hash() itself.
 *
 * @see https://www.drupal.org/node/3581605
 */
final class WeakHashRule implements Rule
{
    private const REPLACEMENT = 'xxh64';

    private const HELP = 'xxHash is faster and is what Drupal standardises on for non-cryptographic hashing.';

    private const LINK = 'https://www.drupal.org/node/3581605';

    /**
     * Weak hashing functions, mapped to the label used in the message.
     */
    private const WEAK_FUNCTIONS = [
        'md5' => 'md5()',
        'sha1' => 'sha1()',
        'crc32' => 'crc32()',
    ];

    /**
     * Algorithm names that are just as weak when passed to hash().
     */
    private const WEAK_ALGORITHMS = ['md5', 'sha1', 'crc32', 'crc32b'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/weak-hash',
            name: 'Weak hash algorithm',
            description: 'Reports md5(), sha1() and crc32() calls, and hash() calls using those algorithms.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    public function lint(LintContext $context): void
    {
        $resolved = $context->getResolvedName();
        if ($resolved === null) {
            return;
        }

        $name = strtolower($resolved->name);
        $label = self::WEAK_FUNCTIONS[$name] ?? null;
        if ($label !== null) {
            $this->reportFunction($context, $label);

            return;
        }

        if ($name === 'hash') {
            $this->reportAlgorithm($context);
        }
    }

    /**
     * Reports a direct call to a weak hashing function.
     */
    private function reportFunction(LintContext $context, string $label): void
    {
        $issue = Issue::new(
            "Use hash() with an xxHash algorithm instead of {$label}.",
            $context->node->span,
        )->withHelp(self::HELP)->withLink(self::LINK);

        $replacement = $this->buildReplacement($context);
        if ($replacement !== null) {
            // Swapping the algorithm changes the digest, so anything already
            // persisted needs migrating rather than just recomputing.
            $issue = $issue->withEdit(TextEdit::replace(
                $context->node->span,
                $replacement,
            )->withSafety(Safety::Unsafe));
        }

        $context->report($issue);
    }

    /**
     * Reports hash() called with a weak algorithm name.
     */
    private function reportAlgorithm(LintContext $context): void
    {
        $algorithm = CallExpression::fromNode($context->file, $context->node)->arguments[0] ?? null;
        if ($algorithm === null || $algorithm->value->kind !== NodeKind::LiteralString) {
            return;
        }

        $name = strtolower(trim($context->file->getText($algorithm->value), characters: '\'"'));
        if (!in_array($name, self::WEAK_ALGORITHMS, strict: true)) {
            return;
        }

        $context->report(
            Issue::new("The '{$name}' algorithm is weak. Use an xxHash algorithm instead.", $algorithm->value->span)
                ->withHelp(self::HELP)
                ->withLink(self::LINK)
                ->withEdit(TextEdit::replace(
                    $algorithm->value->span,
                    "'" . self::REPLACEMENT . "'",
                )->withSafety(Safety::Unsafe)),
        );
    }

    /**
     * Builds a hash() call replacing a single-argument weak hash call.
     *
     * Returns NULL when the call carries extra arguments, because the binary
     * and raw-output flags do not map across.
     */
    private function buildReplacement(LintContext $context): ?string
    {
        $arguments = CallExpression::fromNode($context->file, $context->node)->arguments;
        $argument = count($arguments) === 1 ? $arguments[0] : null;
        if ($argument === null || $argument->unpacked || $argument->name !== null) {
            return null;
        }

        return "hash('" . self::REPLACEMENT . "', " . $context->file->getText($argument->value) . ')';
    }
}
