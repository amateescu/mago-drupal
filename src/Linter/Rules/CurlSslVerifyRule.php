<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Calls;
use amateescu\MagoDrupal\Internal\Values;
use amateescu\MagoDrupal\Linter\CallRule;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallExpression;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function ltrim;
use function preg_match;
use function str_replace;
use function strtolower;

/**
 * Reports `curl_setopt()` that sets `CURLOPT_SSL_VERIFYPEER` to FALSE or 0.
 *
 * Ports DrupalPractice.FunctionCalls.CurlSslVerifier. With the check off, a
 * man in the middle can pose as the server.
 *
 * Coder reads only the first token of the value and matches the written
 * case. This rule reads the whole value and ignores case, so `false`,
 * `(FALSE)` and `0x0` count, and `FALSE ?: TRUE` does not.
 */
final class CurlSslVerifyRule extends CallRule
{
    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/curl-ssl-verify',
            name: 'Curl SSL verification',
            description: 'Reports curl_setopt() calls that turn off CURLOPT_SSL_VERIFYPEER.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::FunctionCall],
        );
    }

    protected function names(): array
    {
        return ['curl_setopt'];
    }

    protected function inspect(LintContext $context, CallExpression $call, string $name): void
    {
        // A spread hides which argument is in which position.
        if (Calls::isUnpacked($call)) {
            return;
        }

        $option = $this->argument($context, $call, 1, 'option');
        if ($option === null || !$this->isVerifyPeer($context->file, $option)) {
            return;
        }

        $value = $this->argument($context, $call, 2, 'value');
        if ($value === null || !$this->isOff($context->file, $value)) {
            return;
        }

        $context->report(Issue::new(
            'Potential security problem: SSL peer verification must not be disabled.',
            $value->span,
        )->withHelp('Pass TRUE, so that curl checks the certificate of the server.'));
    }

    /**
     * Whether the option is the constant. A constant name is case-sensitive.
     */
    private function isVerifyPeer(SourceFile $file, Node $option): bool
    {
        return (
            $option->kind === NodeKind::ConstantAccess
            && ltrim($file->getText($option), characters: '\\') === 'CURLOPT_SSL_VERIFYPEER'
        );
    }

    /**
     * Whether the value is the constant FALSE or a zero integer literal,
     * with or without parentheses.
     */
    private function isOff(SourceFile $file, Node $value): bool
    {
        while ($value->kind === NodeKind::Parenthesized) {
            $inner = $file->getChildren($value)[0] ?? null;
            if ($inner === null) {
                return false;
            }

            $value = Values::unwrap($file, $inner);
        }

        $text = $file->getText($value);
        if ($value->kind === NodeKind::LiteralInteger) {
            // Zero in any base: 0, 00, 0x0, 0b0, 0o0.
            return preg_match('/^0(?:[xXbBoO]?0*)$/', str_replace('_', replace: '', subject: $text)) === 1;
        }

        return (
            ($value->kind === NodeKind::Keyword || $value->kind === NodeKind::ConstantAccess)
            && strtolower(ltrim($text, characters: '\\')) === 'false'
        );
    }
}
