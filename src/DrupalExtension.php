<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal;

use amateescu\MagoDrupal\Analyzer\DrupalPlugin;
use amateescu\MagoDrupal\Internal\DefaultOffRule;
use amateescu\MagoDrupal\Internal\DeprecationTarget;
use amateescu\MagoDrupal\Analyzer\PHPStan\PHPStanIgnoresPlugin;
use amateescu\MagoDrupal\Analyzer\PHPUnit\PHPUnitPlugin;
use amateescu\MagoDrupal\Linter\Rules\AuthorTagRule;
use amateescu\MagoDrupal\Linter\Rules\ClassCommentRule;
use amateescu\MagoDrupal\Linter\Rules\CommentLineLengthRule;
use amateescu\MagoDrupal\Linter\Rules\ConstantPrefixRule;
use amateescu\MagoDrupal\Linter\Rules\DeprecatedTagRule;
use amateescu\MagoDrupal\Linter\Rules\DeprecationMessageRule;
use amateescu\MagoDrupal\Linter\Rules\DiscouragedFunctionRule;
use amateescu\MagoDrupal\Linter\Rules\DocCommentArraySyntaxRule;
use amateescu\MagoDrupal\Linter\Rules\DocCommentRule;
use amateescu\MagoDrupal\Linter\Rules\DocTypeNamespaceRule;
use amateescu\MagoDrupal\Linter\Rules\ElseIfRule;
use amateescu\MagoDrupal\Linter\Rules\EmptyInstallHookRule;
use amateescu\MagoDrupal\Linter\Rules\EnumCaseNameRule;
use amateescu\MagoDrupal\Linter\Rules\ExpectedExceptionTagRule;
use amateescu\MagoDrupal\Linter\Rules\FileCommentRule;
use amateescu\MagoDrupal\Linter\Rules\FullyQualifiedNameRule;
use amateescu\MagoDrupal\Linter\Rules\FunctionCommentRule;
use amateescu\MagoDrupal\Linter\Rules\GenderNeutralCommentRule;
use amateescu\MagoDrupal\Linter\Rules\GlobalFunctionRule;
use amateescu\MagoDrupal\Linter\Rules\GlobalVariableRule;
use amateescu\MagoDrupal\Linter\Rules\HookCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentBlankLineRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentPunctuationRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineVariableCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InsecureUnserializeRule;
use amateescu\MagoDrupal\Linter\Rules\InstallHookLocationRule;
use amateescu\MagoDrupal\Linter\Rules\LinkTextTranslatableRule;
use amateescu\MagoDrupal\Linter\Rules\LongDescriptionPunctuationRule;
use amateescu\MagoDrupal\Linter\Rules\MethodVisibilityRule;
use amateescu\MagoDrupal\Linter\Rules\NullableParamTagRule;
use amateescu\MagoDrupal\Linter\Rules\PostStatementCommentRule;
use amateescu\MagoDrupal\Linter\Rules\PregSecurityRule;
use amateescu\MagoDrupal\Linter\Rules\PropertyNameRule;
use amateescu\MagoDrupal\Linter\Rules\RedundantUseRule;
use amateescu\MagoDrupal\Linter\Rules\RemoteAddressRule;
use amateescu\MagoDrupal\Linter\Rules\RenderCallbackRule;
use amateescu\MagoDrupal\Linter\Rules\SymfonyYamlParseRule;
use amateescu\MagoDrupal\Linter\Rules\TodoCommentRule;
use amateescu\MagoDrupal\Linter\Rules\TranslatableStringRule;
use amateescu\MagoDrupal\Linter\Rules\TranslatedExceptionRule;
use amateescu\MagoDrupal\Linter\Rules\TranslationInHookMenuRule;
use amateescu\MagoDrupal\Linter\Rules\TranslationInHookSchemaRule;
use amateescu\MagoDrupal\Linter\Rules\UnsilencedDeprecationRule;
use amateescu\MagoDrupal\Linter\Rules\UseLeadingBackslashRule;
use amateescu\MagoDrupal\Linter\Rules\VariableCommentRule;
use amateescu\MagoDrupal\Linter\Rules\WatchdogMessageRule;
use amateescu\MagoDrupal\Linter\Rules\WeakHashRule;
use Composer\InstalledVersions;
use InvalidArgumentException;
use Mago\Sdk\Extension;
use Mago\Sdk\Linter\Rule;

use function array_fill_keys;
use function array_filter;
use function array_key_exists;
use function array_keys;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function preg_match;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

/**
 * Builds the complete extension that each worker process advertises.
 *
 * This is the only registration API that a consumer must use. Options are
 * typed arguments here, not rule lists that the caller must assemble.
 *
 * @api
 */
final class DrupalExtension
{
    private const PACKAGE = 'amateescu/mago-drupal';

    private function __construct() {}

    /**
     * The rules that core's `phpcs.xml.dist` turns off. With `--core`, they
     * are off by default.
     */
    private const CORE_OFF = [
        'drupal/inline-comment-blank-line',
        'drupal/inline-comment-punctuation',
        'drupal/long-description-punctuation',
    ];

    /**
     * Builds the extension from the worker's arguments: `--core` when the
     * worker runs on Drupal core, `--root=PATH` for the Drupal document root,
     * `--deprecations=N` for the Drupal major whose removals to report, and
     * `--disable=<code>,<code>` for the rules to turn off by default.
     * `--disable` can be given more than once. Of the others, the last one
     * wins, matching how repeated CLI flags behave.
     *
     * @throws InvalidArgumentException When `--deprecations` is not a major
     *   version, or a code in `--disable` names no rule.
     *
     * @param array<mixed> $arguments
     */
    public static function fromArguments(array $arguments): Extension
    {
        $disabled = [];
        $root = null;
        $deprecations = null;
        foreach (array_filter($arguments, is_string(...)) as $argument) {
            if (str_starts_with($argument, '--root=')) {
                $root = substr($argument, offset: strlen('--root='));
            }

            if (str_starts_with($argument, '--deprecations=')) {
                $deprecations = substr($argument, offset: strlen('--deprecations='));
            }

            if (!str_starts_with($argument, '--disable=')) {
                continue;
            }

            foreach (explode(',', substr($argument, offset: strlen('--disable='))) as $code) {
                if (trim($code) === '') {
                    continue;
                }

                $disabled[] = trim($code);
            }
        }

        if ($deprecations !== null && preg_match('/^[1-9][0-9]*$/', $deprecations) !== 1) {
            throw new InvalidArgumentException(
                "--deprecations takes a Drupal major version such as 12, got \"{$deprecations}\".",
            );
        }

        return self::create(
            core: in_array('--core', $arguments, strict: true),
            disabled: $disabled,
            root: $root,
            deprecations: $deprecations === null ? null : (int) $deprecations,
        );
    }

    /**
     * @param bool $core Turns off the checks that do not apply to Drupal core
     *   itself, and turns off by default the rules that core's
     *   `phpcs.xml.dist` turns off.
     * @param list<string> $disabled The codes of the rules to turn off by
     *   default.
     * @param string|null $root Drupal document root, absolute or relative to
     *   the worker's cwd. Discovered from the cwd when null.
     * @param int|null $deprecations Reports only the Drupal deprecations
     *   removed in this major or earlier, such as 12; every deprecation when
     *   null.
     *
     * @throws InvalidArgumentException When a code in $disabled names no rule,
     *   or $deprecations is not a positive number.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    public static function create(
        bool $core = false,
        array $disabled = [],
        ?string $root = null,
        ?int $deprecations = null,
    ): Extension
    {
        $off = array_fill_keys($core ? [...self::CORE_OFF, ...$disabled] : $disabled, value: true);
        $rules = [];
        foreach (self::linterRules() as $rule) {
            $code = $rule->getDefinition()->code;
            $rules[] = array_key_exists($code, $off) ? new DefaultOffRule($rule) : $rule;
            unset($off[$code]);
        }

        if ($off !== []) {
            throw new InvalidArgumentException(
                'No mago-drupal rule has the code ' . implode(', ', array_keys($off)) . '.',
            );
        }

        return new Extension(
            identifier: self::PACKAGE,
            name: 'Drupal',
            version: self::version(),
            linterRules: $rules,
            analyzerPlugins: [
                new DrupalPlugin($core, $root, $deprecations === null ? null : DeprecationTarget::major($deprecations)),
                new PHPUnitPlugin(),
                new PHPStanIgnoresPlugin(),
            ],
        );
    }

    /**
     * @return list<Rule>
     */
    private static function linterRules(): array
    {
        return [
            new AuthorTagRule(),
            new ClassCommentRule(),
            new CommentLineLengthRule(),
            new ConstantPrefixRule(),
            new DeprecatedTagRule(),
            new DeprecationMessageRule(),
            new DiscouragedFunctionRule(),
            new DocCommentArraySyntaxRule(),
            new DocCommentRule(),
            new DocTypeNamespaceRule(),
            new ElseIfRule(),
            new EmptyInstallHookRule(),
            new EnumCaseNameRule(),
            new ExpectedExceptionTagRule(),
            new FileCommentRule(),
            new FullyQualifiedNameRule(),
            new FunctionCommentRule(),
            new GenderNeutralCommentRule(),
            new GlobalFunctionRule(),
            new GlobalVariableRule(),
            new HookCommentRule(),
            new InlineCommentRule(),
            new InlineCommentBlankLineRule(),
            new InlineCommentPunctuationRule(),
            new InlineVariableCommentRule(),
            new InsecureUnserializeRule(),
            new InstallHookLocationRule(),
            new LinkTextTranslatableRule(),
            new LongDescriptionPunctuationRule(),
            new MethodVisibilityRule(),
            new NullableParamTagRule(),
            new PostStatementCommentRule(),
            new PregSecurityRule(),
            new PropertyNameRule(),
            new RedundantUseRule(),
            new RemoteAddressRule(),
            new RenderCallbackRule(),
            new SymfonyYamlParseRule(),
            new TodoCommentRule(),
            new TranslatableStringRule(),
            new TranslatedExceptionRule(),
            new TranslationInHookMenuRule(),
            new TranslationInHookSchemaRule(),
            new UnsilencedDeprecationRule(),
            new UseLeadingBackslashRule(),
            new VariableCommentRule(),
            new WatchdogMessageRule(),
            new WeakHashRule(),
        ];
    }

    /**
     * The version Composer installed: the tag, or `dev-<branch>` for a
     * checkout. Mago shows it in `mago extension list`.
     */
    private static function version(): string
    {
        // A package loaded without Composer's autoloader has no record.
        if (!InstalledVersions::isInstalled(self::PACKAGE)) {
            return 'unknown';
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE) ?? 'unknown';
    }
}
