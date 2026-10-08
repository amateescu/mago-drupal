<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use amateescu\MagoDrupal\Internal\Arguments;
use amateescu\MagoDrupal\Internal\Types;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\MethodCallAnalysisHook;
use Mago\Sdk\Analyzer\MethodTarget;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Syntax\CallArgument;

use function array_keys;
use function implode;
use function ltrim;
use function preg_match;
use function strlen;
use function substr;

/**
 * Reports a PHPUnit assertion that always passes, given the type Mago
 * already knows for the value.
 *
 * phpstan-phpunit reports the same calls. A type from a docblock counts, as
 * it does for PHPStan. Only always-true assertions are reported here: Mago
 * reports an assertion on a variable that can never pass itself, as
 * `impossible-type-comparison`. One instance checks one assertion method.
 *
 * @internal
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class RedundantAssertionHook implements MethodCallAnalysisHook
{
    public const CODE = 'redundant-assertion';

    /**
     * The assertion methods checked, each with the name of the parameter
     * that takes the value.
     */
    public const METHODS = [
        'assertInstanceOf' => 'actual',
        'assertNotNull' => 'actual',
        'assertNull' => 'actual',
        'assertTrue' => 'condition',
        'assertFalse' => 'condition',
        'assertNotTrue' => 'condition',
        'assertNotFalse' => 'condition',
        'assertIsArray' => 'actual',
        'assertIsBool' => 'actual',
        'assertIsFloat' => 'actual',
        'assertIsInt' => 'actual',
        'assertIsNumeric' => 'actual',
        'assertIsObject' => 'actual',
        'assertIsScalar' => 'actual',
        'assertIsString' => 'actual',
    ];

    private const ASSERT = 'PHPUnit\Framework\Assert';

    /**
     * @param key-of<self::METHODS> $method
     */
    public function __construct(
        private readonly string $method,
    ) {}

    public function getTargets(): array
    {
        return [MethodTarget::exact(self::ASSERT, $this->method)];
    }

    public function getRequirements(): array
    {
        // The subtree and the text say which argument fills which parameter
        // when the call names them.
        return [
            FileAnalysisRequirement::ArgumentTypes,
            FileAnalysisRequirement::TargetSubtree,
            FileAnalysisRequirement::SourceText,
        ];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        if (!$this->anyMayPass($context)) {
            return;
        }

        $argument = $this->argument($context);
        $value = $argument === null ? null : $context->argumentTypes[$argument->index] ?? null;
        $passes = fn(Type $type): bool => $this->passes($context, $type);
        if (
            $argument === null
            || $value === null
            || !$passes($value)
            || !SettledValue::holds($context, $argument->value, $passes)
        ) {
            return;
        }

        // Mago describes a literal `true` as `bool`, so these three, and a
        // literal bool passed to any other, name the one value it is.
        $literal = $value->getLiteralBool();
        $already = match (true) {
            $this->method === 'assertTrue' => 'always `true`',
            $this->method === 'assertFalse' => 'always `false`',
            $this->method === 'assertNull' => 'always `null`',
            $literal !== null => $literal ? 'always `true`' : 'always `false`',
            default => 'already `' . self::describe((string) $value) . '`',
        };
        $context->report(
            Level::Warning,
            self::CODE,
            Issue::new(
                "`{$this->method}()` always passes: the value is {$already}.",
                $context->node->span,
                'always passes',
            )->withHelp(
                'Remove the assertion, or assert something about the value that its type does not already say.',
            ),
        );
    }

    /**
     * The type's description with each top-level member once: Mago can
     * describe a union it did not simplify, such as `int|int`.
     */
    private static function describe(string $description): string
    {
        $members = [];
        $depth = 0;
        $start = 0;
        $length = strlen($description);
        for ($i = 0; $i <= $length; $i++) {
            $char = $i < $length ? $description[$i] : '|';
            $depth += match ($char) {
                '<', '{', '(' => 1,
                '>', '}', ')' => -1,
                default => 0,
            };
            if ($char === '|' && $depth === 0) {
                $members[substr($description, $start, $i - $start)] = true;
                $start = $i + 1;
            }
        }

        return implode('|', array_keys($members));
    }

    /**
     * Whether any argument's type could make the call always pass. Most
     * calls fail this, and it costs less than reading the call's syntax.
     */
    private function anyMayPass(NodeAnalysisContext $context): bool
    {
        foreach ($context->argumentTypes as $type) {
            $may =
                $type !== null
                && (
                    $this->method === 'assertInstanceOf'
                        ? Types::objectsOnly($type)
                        : AlwaysPasses::check($this->method, $type)
                );
            if ($may) {
                return true;
            }
        }

        return false;
    }

    /**
     * The argument holding the asserted value, or null when the call names
     * its method through a variable, which Mago may have resolved to more
     * than one method.
     */
    private function argument(NodeAnalysisContext $context): ?CallArgument
    {
        $named = '/(?:->|::)\s*' . $this->method . '\s*\(/i';
        if (preg_match($named, $context->source->getText($context->node->span)) !== 1) {
            return null;
        }

        $position = $this->method === 'assertInstanceOf' ? 1 : 0;

        return Arguments::argument($context, $position, self::METHODS[$this->method]);
    }

    /**
     * Whether a value of this type always passes the assertion.
     */
    private function passes(NodeAnalysisContext $context, Type $type): bool
    {
        return $this->method === 'assertInstanceOf'
            ? self::isInstance($context, $type)
            : AlwaysPasses::check($this->method, $type);
    }

    /**
     * Whether every member of the value's type is an instance of the
     * expected class.
     */
    private static function isInstance(NodeAnalysisContext $context, Type $value): bool
    {
        $expected = ltrim((string) Arguments::type($context, 0, 'expected')?->getLiteralString(), characters: '\\');

        return (
            $expected !== ''
            && Types::objectsOnly($value)
            && $context->types->isContainedBy($value, Type::namedObject($expected))
        );
    }
}
