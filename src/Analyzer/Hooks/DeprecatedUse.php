<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Analyzer\Checks\Reporter;
use amateescu\MagoDrupal\Internal\DeprecationScopes;
use amateescu\MagoDrupal\Internal\DeprecationTarget;
use amateescu\MagoDrupal\Internal\FileMembers;
use amateescu\MagoDrupal\Internal\InheritedDeprecation;
use amateescu\MagoDrupal\Internal\NamedFunctions;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\Metadata\ClassLikeKind;
use Mago\Sdk\Analyzer\NodeAnalysisContext;
use Mago\Sdk\Span;

use function ltrim;
use function strlen;
use function strtolower;

/**
 * Reports a use of a deprecated symbol from one of the plugin's deprecation
 * hooks.
 *
 * Issue filters never see hook issues, so the scopes and the target that
 * Mago's own deprecation codes get are applied here.
 *
 * @internal
 */
final class DeprecatedUse
{
    /**
     * Whitespace and comments, which PHP accepts between the tokens of an
     * access such as `Foo::BAR`. `#[` opens an attribute, not a comment. The
     * slashes are escaped so any pattern delimiter can hold it.
     */
    public const GAP = '(?:\s++|\/\*(?:[^*]|\*(?!\/))*+\*\/|(?:\/\/|#(?!\[))[^\n]*+)*+';

    public function __construct(
        private readonly DeprecationTarget $target,
    ) {}

    /**
     * Reports the use unless a deprecation scope covers it or the target
     * leaves its removal version for later.
     *
     * @param string $text The `@deprecated` text, shown as the help.
     */
    public function report(NodeAnalysisContext $context, string $code, Span $span, string $message, string $text): void
    {
        if (!$this->target->keeps($text) || self::covered($context, $span)) {
            return;
        }

        (new Reporter($context))->warning($code, Reporter::issue($message, $span, 'Deprecated ' . $text));
    }

    /**
     * Whether a deprecation scope covers the span: the scopes Mago's own
     * deprecation codes get, overrides of deprecated methods included.
     */
    public static function covered(NodeAnalysisContext $context, Span $span): bool
    {
        $source = $context->source;
        $contents = $source->contents;

        return (
            DeprecationScopes::marked($contents) && DeprecationScopes::of($contents)->covers($span)
            || InheritedDeprecation::covers($context->codebase, $source->path, NamedFunctions::of($contents), $span)
        );
    }

    /**
     * The class a `Foo::`, `self::`, `static::` or `parent::` prefix names,
     * or null when it names none. The prefix is the text before `::`,
     * starting at the offset.
     */
    public static function className(NodeAnalysisContext $context, string $prefix, int $offset): ?string
    {
        $source = $context->source;
        $keyword = strtolower($prefix);
        if ($keyword === 'self' || $keyword === 'static' || $keyword === 'parent') {
            $at = new Span($offset, $offset + strlen($prefix));
            $enclosing = FileMembers::of($source)->classAt($context->codebase, $source, $at);

            return $keyword === 'parent' ? $enclosing?->directParentClass : $enclosing?->name;
        }

        $resolved = $source->getResolvedName(new Span($offset, $offset + strlen($prefix)));

        return $resolved === null ? null : ltrim($resolved->name, characters: '\\');
    }

    /**
     * The kind and name of a class-like, as a message names it: "interface
     * `Foo`".
     */
    public static function describe(Codebase $codebase, string $name): string
    {
        $kind = match ($codebase->getClassLike($name)?->kind) {
            ClassLikeKind::Interface => 'interface',
            ClassLikeKind::Trait => 'trait',
            ClassLikeKind::Enum => 'enum',
            default => 'class',
        };

        return $kind . ' `' . self::originalName($codebase, $name) . '`';
    }

    /**
     * A class-like's name as declared. Metadata and lineage names are
     * lowercased.
     */
    public static function originalName(Codebase $codebase, string $name): string
    {
        return $codebase->getClassLike($name)->originalName ?? $name;
    }

    /**
     * The class and every class and interface above it, lowercased, so a
     * member inherited from an ancestor is found under the ancestor.
     *
     * @return list<string>
     */
    public static function lineage(Codebase $codebase, string $class): array
    {
        $metadata = $codebase->getClassLike($class);

        return (
            $metadata === null
                ? [strtolower($class)]
                : [$metadata->name, ...$metadata->parentClasses, ...$metadata->parentInterfaces]
        );
    }
}
