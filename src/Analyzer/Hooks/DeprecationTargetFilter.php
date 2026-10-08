<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Analyzer\Hooks;

use amateescu\MagoDrupal\Internal\AnalysisMemo;
use amateescu\MagoDrupal\Internal\DeprecatedTag;
use amateescu\MagoDrupal\Internal\DeprecationTarget;
use Mago\Sdk\Analyzer\Codebase;
use Mago\Sdk\Analyzer\IssueFilterContext;
use Mago\Sdk\Analyzer\IssueFilterDecision;
use Mago\Sdk\Analyzer\IssueFilterHook;
use Mago\Sdk\Analyzer\Metadata\ConstantMetadata;
use Mago\Sdk\SourceLocation;

use function explode;
use function file_get_contents;
use function is_file;
use function preg_match;
use function str_contains;
use function strrchr;
use function strtolower;
use function substr;

/**
 * Drops Mago's deprecation issues that a later Drupal major removes.
 *
 * Mago's message names the deprecated symbol in backticks, as in "Call to
 * deprecated method: `Foo::bar`.", but not the `@deprecated` text. The
 * symbol's metadata gives the file and span of its declaration, and the text
 * is read from the docblock above it. A symbol whose text cannot be read,
 * such as one of Mago's own PHP stubs, keeps its issue.
 *
 * @internal
 */
final class DeprecationTargetFilter implements IssueFilterHook
{
    /**
     * @var AnalysisMemo<string|null>
     */
    private AnalysisMemo $texts;

    public function __construct(
        private readonly DeprecationTarget $target,
    ) {
        $this->texts = new AnalysisMemo();
    }

    public function getCodes(): array
    {
        return [
            'deprecated-class',
            'deprecated-class-constant',
            'deprecated-constant',
            'deprecated-function',
            'deprecated-method',
            'deprecated-property',
            'deprecated-trait',
        ];
    }

    public function filterIssue(IssueFilterContext $context): IssueFilterDecision
    {
        $matches = [];
        if (preg_match('/`([^`]+)`/', $context->issue->message, $matches) !== 1) {
            return IssueFilterDecision::Keep;
        }

        $code = (string) $context->issue->code;
        $symbol = $matches[1];
        $text = $this->texts->get(
            $context->codebase,
            $code . ' ' . strtolower($symbol),
            static fn(): ?string => self::text($context, $code, $symbol),
        );

        return $text === null || $this->target->keeps($text) ? IssueFilterDecision::Keep : IssueFilterDecision::Remove;
    }

    /**
     * The `@deprecated` text of the symbol, or null when it cannot be read.
     */
    private static function text(IssueFilterContext $context, string $code, string $symbol): ?string
    {
        $location = self::declaration($context->codebase, $code, $symbol);
        if ($location?->file === null) {
            return null;
        }

        $source = self::source($context, $location->file);

        return $source === null ? null : DeprecatedTag::above($source, $location->span->start);
    }

    /**
     * The bytes of a file, or null when it cannot be read.
     *
     * In an editor session the file being analyzed can differ from the one
     * on disk, so its own bytes come from the context.
     */
    private static function source(IssueFilterContext $context, string $file): ?string
    {
        if ($file === $context->file) {
            return $context->contents;
        }

        $source = is_file($file) ? file_get_contents($file) : false;

        return $source === false ? null : $source;
    }

    private static function declaration(Codebase $codebase, string $code, string $symbol): ?SourceLocation
    {
        $member = self::member($symbol);

        return match ($code) {
            'deprecated-method' => $member === null ? null : $codebase->getDeclaringMethod(...$member)?->location,
            'deprecated-property' => $member === null ? null : self::property($codebase, $member),
            'deprecated-class-constant' => $member === null ? null : $codebase->getClassConstant(...$member)?->location,
            'deprecated-function' => $codebase->getFunction($symbol)?->location,
            // PHP falls back to a global constant when the namespaced name is
            // not defined, and Mago's message names the namespaced one.
            'deprecated-constant' => (
                $codebase->getConstant($symbol) ?? self::globalConstant($codebase, $symbol)
            )?->location,
            default => $codebase->getClassLike($symbol)?->location,
        };
    }

    /**
     * Where a property is declared. A property declared in a class body has
     * only the location of its name.
     *
     * @param array{string, string} $member
     */
    private static function property(Codebase $codebase, array $member): ?SourceLocation
    {
        $property = $codebase->getDeclaringProperty(...$member);

        return $property->location ?? $property?->nameLocation;
    }

    /**
     * The class and the member of a `Class::member` name, or null for a name
     * without a class. The codebase finds a member an ancestor declares.
     *
     * @return array{string, string}|null
     */
    private static function member(string $symbol): ?array
    {
        if (!str_contains($symbol, '::')) {
            return null;
        }

        [$class, $member] = explode('::', $symbol, limit: 2);

        return [$class, $member];
    }

    private static function globalConstant(Codebase $codebase, string $symbol): ?ConstantMetadata
    {
        $tail = strrchr($symbol, needle: '\\');

        return $tail === false ? null : $codebase->getConstant(substr($tail, offset: 1));
    }
}
