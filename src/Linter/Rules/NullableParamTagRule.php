<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Linter\Rules;

use amateescu\MagoDrupal\Internal\Docblocks;
use amateescu\MagoDrupal\Internal\DocblockTag;
use amateescu\MagoDrupal\Internal\DocType;
use Mago\Sdk\Linter\LintContext;
use Mago\Sdk\Linter\Rule;
use Mago\Sdk\Linter\RuleDefinition;
use Mago\Sdk\Reporting\Issue;
use Mago\Sdk\Reporting\Level;
use Mago\Sdk\Reporting\Safety;
use Mago\Sdk\Reporting\TextEdit;
use Mago\Sdk\Syntax\Node;
use Mago\Sdk\Syntax\NodeKind;

use function array_key_exists;
use function in_array;
use function ltrim;
use function preg_match;
use function preg_quote;
use function str_ends_with;
use function str_starts_with;
use function stripos;
use function strlen;
use function strpos;
use function strspn;
use function strtolower;
use function substr;
use function trim;

/**
 * Reports an `@param` type without null on a parameter that defaults to
 * NULL and has no native type.
 *
 * The docblock is then the only type of the parameter, and tools read it
 * differently. PHPStan adds the null from the default. Mago accepts the
 * default but keeps the documented type, so it reports an explicit NULL
 * argument and misses a null in the body.
 *
 * @mago-expect lint:cyclomatic-complexity
 * @mago-expect lint:kan-defect
 */
final class NullableParamTagRule implements Rule
{
    /**
     * Tags whose type wins over `@param`, in the order PHPStan reads them.
     */
    private const PARAM_TAGS = ['phpstan-param', 'psalm-param', 'param'];

    private const TEMPLATE_TAGS = [
        'template',
        'template-covariant',
        'template-contravariant',
        'phpstan-template',
        'psalm-template',
    ];

    private const WHITESPACE = " \t\n\r\v\f";

    public function getDefinition(): RuleDefinition
    {
        return new RuleDefinition(
            code: 'drupal/nullable-param-tag',
            name: 'Nullable param tag',
            description: 'Reports an @param type without null on an untyped parameter that defaults to NULL.',
            defaultLevel: Level::Warning,
            defaultEnabled: true,
            targets: [NodeKind::Function, NodeKind::Method],
        );
    }

    public function lint(LintContext $context): void
    {
        $list = self::parameterList($context);
        // Most functions have no NULL default, and the text check skips them
        // before the docblock or the parameter nodes are read.
        if ($list === null || stripos($context->file->getText($list), needle: 'null') === false) {
            return;
        }

        $docblock = Docblocks::attachedTo($context->file, $context->node);
        if ($docblock === null) {
            return;
        }

        $tags = Docblocks::tags($context->file, $docblock);
        $templates = self::templates($tags);
        foreach (self::untypedNullDefaults($context, $list) as $variable) {
            [$tag, $type] = self::paramTag($tags, $variable) ?? [null, null];
            if ($tag === null || $type === null || self::admitsNull($type, $templates)) {
                continue;
            }

            $issue = Issue::new(
                "{$variable} defaults to NULL, so its @{$tag->name} type must include null.",
                $tag->contentSpan(),
            );
            $end = self::typeEnd($tag, $type);
            $issue = $end === null
                ? $issue->withHelp('Add |null to the end of the type.')
                : $issue
                    ->withHelp("Write the type as {$type}|null.")
                    ->withEdit(TextEdit::insert($end, '|null')->withSafety(Safety::Safe));

            $context->report($issue);
        }
    }

    /**
     * The node that holds the function's parameters.
     */
    private static function parameterList(LintContext $context): ?Node
    {
        foreach ($context->file->getChildren($context->node) as $child) {
            if ($child->kind === NodeKind::FunctionLikeParameterList) {
                return $child;
            }
        }

        return null;
    }

    /**
     * The variables of the parameters that default to NULL and have no
     * native type, such as `$rel` in `toUrl($rel = NULL)`.
     *
     * @return list<string>
     */
    private static function untypedNullDefaults(LintContext $context, Node $list): array
    {
        $variables = [];
        foreach ($context->file->getChildren($list) as $parameter) {
            $variable = null;
            $nullDefault = false;
            $typed = false;
            foreach ($context->file->getChildren($parameter) as $part) {
                if ($part->kind === NodeKind::Hint) {
                    $typed = true;
                    break;
                }

                if ($part->kind === NodeKind::DirectVariable) {
                    $variable = $context->file->getText($part);
                    continue;
                }

                if ($part->kind === NodeKind::FunctionLikeParameterDefaultValue) {
                    $default = ltrim(
                        trim(ltrim(trim($context->file->getText($part)), characters: '=')),
                        characters: '\\',
                    );
                    $nullDefault = strtolower($default) === 'null';
                }
            }

            if ($typed || !$nullDefault || $variable === null) {
                continue;
            }

            $variables[] = $variable;
        }

        return $variables;
    }

    /**
     * The tag that documents the variable, a prefixed one first, with its
     * type. The type is null when the tag has none.
     *
     * @param list<DocblockTag> $tags
     * @return array{DocblockTag, ?string}|null
     */
    private static function paramTag(array $tags, string $variable): ?array
    {
        $pattern = '/^&?(?:\.\.\.)?' . preg_quote($variable, delimiter: '/') . '(?![A-Za-z0-9_])/';
        foreach (self::PARAM_TAGS as $name) {
            foreach ($tags as $tag) {
                if ($tag->name !== $name) {
                    continue;
                }

                $content = $tag->content();
                $type = DocType::leading($content);
                $rest = ltrim(substr($content, $type === null ? 0 : strlen($type)));
                if (preg_match($pattern, $rest) === 1) {
                    return [$tag, $type];
                }
            }
        }

        return null;
    }

    /**
     * The names the docblock declares with `@template` and its variants.
     *
     * @param list<DocblockTag> $tags
     * @return array<string, true>
     */
    private static function templates(array $tags): array
    {
        $names = [];
        foreach ($tags as $tag) {
            $matches = [];
            if (
                in_array($tag->name, self::TEMPLATE_TAGS, strict: true)
                && preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', $tag->content(), $matches) === 1
            ) {
                $names[$matches[0]] = true;
            }
        }

        return $names;
    }

    /**
     * Whether a top-level member of the union admits null: `null`, `mixed`,
     * a `?T` shorthand or a template. A null inside a generic, as in
     * `array<string|null>`, does not count.
     *
     * @param array<string, true> $templates
     */
    private static function admitsNull(string $type, array $templates): bool
    {
        foreach (DocType::members($type) as $member) {
            if (str_starts_with($member, '(') && str_ends_with($member, ')')) {
                if (self::admitsNull(substr($member, offset: 1, length: -1), $templates)) {
                    return true;
                }

                continue;
            }

            if (
                str_starts_with($member, '?')
                || in_array(strtolower($member), ['null', 'mixed'], strict: true)
                || array_key_exists($member, $templates)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * The offset right after the type, or null when the type goes on past
     * the tag's first line, where appending to it could land inside a
     * comment line prefix.
     */
    private static function typeEnd(DocblockTag $tag, string $type): ?int
    {
        $line = $tag->lines[0];
        $indent = strspn($line->text, self::WHITESPACE);
        $position = strpos($line->text, $type, $indent);

        return $position === $indent ? $line->offset + $position + strlen($type) : null;
    }
}
