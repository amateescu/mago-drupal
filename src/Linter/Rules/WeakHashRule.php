<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Values;
use amateescu\MagoDrupal\Linter\CallRule;
use Mago\Sdk\Linter\LintContext;
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

/**
 * Reports an md5(), sha1() or crc32() call, and suggests hash() with xxHash.
 *
 * Ports the disallowed-call configuration in Drupal core's phpstan.neon.dist.
 * That includes the variant that passes a weak algorithm name to hash().
 *
 * @see https://www.drupal.org/node/3581605
 */
final class WeakHashRule extends CallRule
{
    private const REPLACEMENT = 'xxh64';

    private const HELP = 'xxHash is faster, and Drupal uses it as the standard for non-cryptographic hashing.';

    private const LINK = 'https://www.drupal.org/node/3581605';

    /**
     * Algorithm names that are as weak when a call passes them to hash().
     */
    private const WEAK_ALGORITHMS = ['md5', 'sha1', 'crc32', 'crc32b'];

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/weak-hash',
            name: 'Weak hash algorithm',
            description: 'Reports an md5(), sha1() or crc32() call, and a hash() call that uses one of those algorithms.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['md5', 'sha1', 'crc32', 'hash'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        if ($name === 'hash') {
            $this->reportAlgorithm($context, $call);

            return;
        }

        $this->reportFunction($context, $call, "{$name}()");
    }

    /**
     * Reports a direct call to a weak hash function.
     */
    private function reportFunction(LintContext $context, CallExpression $call, string $label): void
    {
        $issue = Issue::new(
            "Use hash() with an xxHash algorithm instead of {$label}.",
            $context->node->span,
        )->withHelp(self::HELP)->withLink(self::LINK);

        $replacement = $this->buildReplacement($context, $call);
        if ($replacement !== null) {
            // A change of algorithm changes the digest. A stored digest must
            // be migrated, not only computed again.
            $issue = $issue->withEdit(TextEdit::replace(
                $context->node->span,
                $replacement,
            )->withSafety(Safety::Unsafe));
        }

        $context->report($issue);
    }

    /**
     * Reports a hash() call with a weak algorithm name.
     */
    private function reportAlgorithm(LintContext $context, CallExpression $call): void
    {
        $algorithm = $this->argument($context, $call, 0, parameter: 'algo');
        if ($algorithm === null || $algorithm->kind !== NodeKind::LiteralString) {
            return;
        }

        $name = strtolower((string) Values::literalString($context->file, $algorithm));
        if (!in_array($name, self::WEAK_ALGORITHMS, strict: true)) {
            return;
        }

        $context->report(
            Issue::new("The '{$name}' algorithm is weak. Use an xxHash algorithm instead.", $algorithm->span)
                ->withHelp(self::HELP)
                ->withLink(self::LINK)
                ->withEdit(TextEdit::replace(
                    $algorithm->span,
                    "'" . self::REPLACEMENT . "'",
                )->withSafety(Safety::Unsafe)),
        );
    }

    /**
     * Builds a hash() call that replaces a single-argument weak hash call.
     *
     * Returns NULL when the call has more arguments, because the binary and
     * raw-output flags do not map across.
     */
    private function buildReplacement(LintContext $context, CallExpression $call): ?string
    {
        $argument = count($call->arguments) === 1 ? $this->argument($context, $call, 0) : null;
        if ($argument === null) {
            return null;
        }

        return "hash('" . self::REPLACEMENT . "', " . $context->file->getText($argument) . ')';
    }
}
