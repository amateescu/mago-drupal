<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\PHPUnit;

use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Internal\ClassFacts;
use amateescu\MagoDrupal\Internal\DeclaredClass;
use Mago\Sdk\Analyzer\FileAnalysisRequirement;
use Mago\Sdk\Analyzer\Metadata\MethodFields;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Analyzer\NodeAnalysisHook;
use Mago\Sdk\Analyzer\Type;
use Mago\Sdk\Analyzer\Type\NamedObjectType;
use Mago\Sdk\SourceLocation;
use Mago\Sdk\Syntax\NodeKind;

use function implode;
use function in_array;
use function ltrim;
use function preg_match;
use function str_starts_with;
use function strtolower;

/**
 * Reports a property, parameter or return type documented as a union of a
 * prophecy and the class it prophesizes.
 *
 * `@var Foo|ProphecyInterface` is an old way to document a prophecy, but a
 * prophecy is never a `Foo`, and ProphecyCallProvider leaves such a union
 * alone. Every call on it is then reported, on one half or the other, far from
 * the docblock that causes it. This hook reports the docblock once, with the
 * type to use instead.
 *
 * @internal
 */
final class ProphecyUnionHook implements NodeAnalysisHook
{
    public const CODE = 'prophecy-union';

    private const PROPHECIES = ['prophecy\prophecy\prophecyinterface', 'prophecy\prophecy\objectprophecy'];

    private const PROPHECY_NAMESPACE = 'Prophecy\\';

    /**
     * A `@var`, `@param` or `@return` type that holds a `|` and names Prophecy.
     */
    private const UNION_TAG = '/@(?:var|param|return)\s+(?=[^\s*]*\|)[^\s*]*Prophecy/';

    private const METHOD_FIELDS =
        MethodFields::NAMES | MethodFields::LOCATIONS | MethodFields::PARAMETERS | MethodFields::RETURN_TYPES;

    public function getTargets(): array
    {
        return [NodeKind::Class_, NodeKind::Trait];
    }

    public function getRequirements(): array
    {
        // Mago ships a file's text once for all hooks, and other hooks
        // already ask for it.
        return [FileAnalysisRequirement::SourceText];
    }

    public function analyze(NodeAnalysisContext $context): void
    {
        $file = $context->source;
        if (preg_match(self::UNION_TAG, $file->getText($context->node->span)) !== 1) {
            return;
        }

        $class = DeclaredClass::resolve($file, $context->node, $context->codebase);
        if ($class === null) {
            return;
        }

        $reporter = new Reporter($context);
        foreach ((new ClassFacts($class, $context->codebase))->properties() as $property) {
            self::check($reporter, $property->name, $property->type?->type, $property->nameLocation);
        }

        $methods = $context->codebase->findMethods(
            class: $class->name,
            fields: self::METHOD_FIELDS,
            declaredOnly: true,
        );
        foreach ($methods as $method) {
            foreach ($method->parameters ?? [] as $parameter) {
                self::check($reporter, $parameter->name, $parameter->type?->type, $parameter->nameLocation);
            }

            $name = $method->originalName ?? $method->method->member;
            self::check($reporter, "{$name}()", $method->returnType?->type, $method->nameLocation);
        }
    }

    private static function check(Reporter $reporter, string $what, ?Type $type, ?SourceLocation $where): void
    {
        $classes = self::prophesized($type);
        if ($classes === [] || $where === null) {
            return;
        }

        $class = implode('|', $classes);
        $reporter->warning(self::CODE, Reporter::issue(
            "A prophecy is never a `{$class}`, but `{$what}` is documented as either.",
            $where,
            "Document it as `\\Prophecy\\Prophecy\\ObjectProphecy<\\{$classes[0]}>`, and call `reveal()` where the object itself is needed.",
        ));
    }

    /**
     * The other classes in a union that also holds a prophecy, or none.
     *
     * @return list<string>
     */
    private static function prophesized(?Type $type): array
    {
        $prophecy = false;
        $classes = [];
        foreach ($type->atomicTypes ?? [] as $atomic) {
            if (!$atomic instanceof NamedObjectType) {
                continue;
            }

            $name = ltrim($atomic->name, characters: '\\');
            if (in_array(strtolower($name), self::PROPHECIES, strict: true)) {
                $prophecy = true;
                continue;
            }

            if (!str_starts_with($name, self::PROPHECY_NAMESPACE)) {
                $classes[] = $name;
            }
        }

        return $prophecy ? $classes : [];
    }
}
