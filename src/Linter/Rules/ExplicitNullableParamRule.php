<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Values;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\PHPVersion;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Span;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;
use Mago\Sdk\Syntax\SourceFile;

use function in_array;
use function ltrim;
use function strcasecmp;
use function strtolower;
use function substr;
use function trim;

/**
 * Reports a typed parameter with a null default whose type doesn't allow
 * null.
 *
 * Ports SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.
 * The fix writes the type that PHP already gives the parameter, so it is
 * safe.
 *
 * @mago-expect lint:cyclomatic-complexity
 */
final class ExplicitNullableParamRule implements Rule
{
    private ?PHPVersion $php82 = null;

    private ?PHPVersion $php84 = null;

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/explicit-nullable-param',
            name: 'Explicit nullable param',
            description: "Reports a typed parameter with a null default whose type doesn't allow null.",
            defaultLevel: Level::Error,
            defaultEnabled: true,
            targets: [NodeKind::FunctionLikeParameter],
        );
    }

    public function lint(LintContext $context): void
    {
        $file = $context->file;

        // From PHP 8.4, Mago's own explicit-nullable-param runs and reports
        // the same parameters. Below 8.4 it doesn't run at all.
        $this->php84 ??= PHPVersion::fromParts(8, 4);
        if ($file->phpVersion->isAtLeast($this->php84)) {
            return;
        }

        // A null default is the last token of the parameter. Most parameters
        // end with something else, and this test skips their children.
        $end = $context->node->span->end;
        if (strcasecmp(substr($file->contents, offset: $end - 4, length: 4), 'null') !== 0) {
            return;
        }

        $hint = null;
        $variable = null;
        $default = null;
        foreach ($file->getChildren($context->node) as $child) {
            match ($child->kind) {
                NodeKind::Hint => $hint = $child,
                NodeKind::DirectVariable => $variable = $child,
                NodeKind::FunctionLikeParameterDefaultValue => $default = $child,
                default => null,
            };
        }

        if ($hint === null || $variable === null || $default === null) {
            return;
        }

        if (!self::isNull($file, $default) || self::allowsNull($file, $hint)) {
            return;
        }

        $name = $file->getText($variable);
        $issue = Issue::new(
            "Make the type of {$name} nullable, since its default is null.",
            new Span($hint->span->start, $variable->span->end),
        )->withHelp("PHP 8.4 deprecates a null default on a type that doesn't allow null.");

        $edit = $this->edit($file, $hint);
        $context->report($edit === null ? $issue : $issue->withEdit($edit));
    }

    /**
     * Whether the default value is `null`, in any case and with or without a
     * leading backslash.
     */
    private static function isNull(SourceFile $file, Node $default): bool
    {
        $value = $file->getChildren($default)[0] ?? null;
        if ($value === null) {
            return false;
        }

        $value = Values::unwrap($file, $value);
        if ($value->kind !== NodeKind::Keyword && $value->kind !== NodeKind::ConstantAccess) {
            return false;
        }

        return strtolower(ltrim($file->getText($value), characters: '\\')) === 'null';
    }

    /**
     * Whether a type allows null: `?T`, `mixed`, `null`, or a union with
     * one of those.
     */
    private static function allowsNull(SourceFile $file, Node $hint): bool
    {
        $type = $file->getChildren($hint)[0] ?? null;
        if ($type?->kind === NodeKind::NullableHint) {
            return true;
        }

        if ($type?->kind === NodeKind::UnionHint) {
            foreach ($file->getChildren($type) as $member) {
                if ($member->kind === NodeKind::Hint && self::allowsNull($file, $member)) {
                    return true;
                }
            }

            return false;
        }

        return in_array(strtolower(trim($file->getText($hint))), ['null', 'mixed'], strict: true);
    }

    /**
     * Writes null into the type in the form of the drupal preset of `mago
     * format`: `?T` for one type, `A|B|null` for a union, and `(A&B)|null`
     * for an intersection. PHP accepts the last form from 8.2, so there is
     * no fix below that.
     */
    private function edit(SourceFile $file, Node $hint): ?TextEdit
    {
        $type = $file->getChildren($hint)[0] ?? null;
        if ($type?->kind === NodeKind::UnionHint) {
            return TextEdit::insert($hint->span->end, '|null');
        }

        if ($type?->kind === NodeKind::IntersectionHint) {
            $this->php82 ??= PHPVersion::fromParts(8, 2);

            return $file->phpVersion->isAtLeast($this->php82)
                ? TextEdit::replace($hint->span, '(' . $file->getText($hint) . ')|null')
                : null;
        }

        return TextEdit::insert($hint->span->start, '?');
    }
}
