<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Span;

use function implode;
use function preg_match;
use function str_starts_with;
use function strlen;

/**
 * Reports `expects()` or `method()` on a value whose type names no PHPUnit
 * double.
 *
 * MockUnionProvider sees one type of a union at a time, so it answers for a
 * value typed `X` alone just as for the `X` half of `X|MockObject`. This hook
 * sees the whole receiver type. When the provider typed the call and no part
 * of the receiver is a PHPUnit type, the value is a mock at runtime but its
 * declared type leaves `MockObject` out. PHPStan reports these calls as
 * undefined methods.
 *
 * @internal
 */
final class PlainMockCallHook implements MethodCallAnalysisHook
{
    public const CODE = 'mock-call-on-plain-type';

    /**
     * @param 'expects'|'method' $method
     */
    public function __construct(
        private readonly string $method,
    ) {}

    public function getTargets(): array
    {
        return [MethodTarget::anyClass($this->method)];
    }

    public function getRequirements(): array
    {
        // Mago ships a file's text once for all hooks, and other hooks
        // already ask for it.
        return [
            FileAnalysisRequirement::ReceiverType,
            FileAnalysisRequirement::TargetExpressionTypes,
            FileAnalysisRequirement::SourceText,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        // Any other result means the provider declined and Mago reported the
        // call itself.
        if (Types::names($context->targetType) !== [MockUnionProvider::INVOCATION_MOCKER]) {
            return;
        }

        $classes = Types::names($context->receiverType);
        if ($classes === []) {
            return;
        }

        foreach ($classes as $class) {
            if (
                str_starts_with($class, MockUnionProvider::PHPUNIT)
                || $context->codebase->getDeclaringMethod($class, $this->method) !== null
            ) {
                return;
            }
        }

        $type = implode('|', $classes);
        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                "`{$this->method}()` is called on a mock typed as `{$type}`, which does not declare it.",
                $this->nameSpan($context),
            )->withHelp(
                "Type the mock as `{$type}&\\PHPUnit\\Framework\\MockObject\\MockObject` where it is declared, such as the property's `@var` or the helper's return type.",
            ),
        );
    }

    /**
     * The span of the method name, where Mago reports its own method issues.
     *
     * The call's text ends in the method name and its arguments. Neither a
     * one-argument `expects()` nor a quoted `method()` name holds another
     * such call, so the last one in the text is this call's.
     */
    private function nameSpan(NodeAnalysisContext $context): Span
    {
        $span = $context->node->span;
        $matches = [];
        $pattern = '/^(.*->\s*)' . $this->method . '\s*\(/is';
        if (preg_match($pattern, $context->source->getText($span), $matches) !== 1) {
            return $span;
        }

        $start = $span->start + strlen($matches[1]);

        return new Span($start, $start + strlen($this->method));
    }
}
