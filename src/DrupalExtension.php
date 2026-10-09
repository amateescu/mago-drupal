<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal;

use amateescu\MagoDrupal\Analyzer\DrupalPlugin;
use amateescu\MagoDrupal\Internal\DefaultOffRule;
use amateescu\MagoDrupal\Linter\Rules\AuthorTagRule;
use amateescu\MagoDrupal\Linter\Rules\ByteOrderMarkRule;
use amateescu\MagoDrupal\Linter\Rules\CaseBreakBlankLineRule;
use amateescu\MagoDrupal\Linter\Rules\CaseFallThroughRule;
use amateescu\MagoDrupal\Linter\Rules\CaseSemicolonRule;
use amateescu\MagoDrupal\Linter\Rules\ClassCommentRule;
use amateescu\MagoDrupal\Linter\Rules\ClassNameAcronymRule;
use amateescu\MagoDrupal\Linter\Rules\ClassPrefixRule;
use amateescu\MagoDrupal\Linter\Rules\CommentInExpressionRule;
use amateescu\MagoDrupal\Linter\Rules\CommentLineLengthRule;
use amateescu\MagoDrupal\Linter\Rules\ConstantPrefixRule;
use amateescu\MagoDrupal\Linter\Rules\ConstPrefixRule;
use amateescu\MagoDrupal\Linter\Rules\CurlSslVerifyRule;
use amateescu\MagoDrupal\Linter\Rules\DefineNameRule;
use amateescu\MagoDrupal\Linter\Rules\DeprecatedTagRule;
use amateescu\MagoDrupal\Linter\Rules\DeprecationMessageRule;
use amateescu\MagoDrupal\Linter\Rules\DiscouragedFunctionRule;
use amateescu\MagoDrupal\Linter\Rules\DocCommentArraySyntaxRule;
use amateescu\MagoDrupal\Linter\Rules\DocCommentRule;
use amateescu\MagoDrupal\Linter\Rules\DocTypeNamespaceRule;
use amateescu\MagoDrupal\Linter\Rules\ElseIfRule;
use amateescu\MagoDrupal\Linter\Rules\EmptyInstallHookRule;
use amateescu\MagoDrupal\Linter\Rules\EmptyPhpTagsRule;
use amateescu\MagoDrupal\Linter\Rules\EmptySwitchRule;
use amateescu\MagoDrupal\Linter\Rules\EnumCaseNameRule;
use amateescu\MagoDrupal\Linter\Rules\ExpectedExceptionTagRule;
use amateescu\MagoDrupal\Linter\Rules\FileCommentRule;
use amateescu\MagoDrupal\Linter\Rules\FileEncodingRule;
use amateescu\MagoDrupal\Linter\Rules\FileStartWhitespaceRule;
use amateescu\MagoDrupal\Linter\Rules\FormAlterCommentRule;
use amateescu\MagoDrupal\Linter\Rules\FullyQualifiedNameRule;
use amateescu\MagoDrupal\Linter\Rules\FunctionCommentRule;
use amateescu\MagoDrupal\Linter\Rules\FunctionPrefixRule;
use amateescu\MagoDrupal\Linter\Rules\GenderNeutralCommentRule;
use amateescu\MagoDrupal\Linter\Rules\GlobalConstantRule;
use amateescu\MagoDrupal\Linter\Rules\GlobalFunctionRule;
use amateescu\MagoDrupal\Linter\Rules\GlobalVariableRule;
use amateescu\MagoDrupal\Linter\Rules\HookAttributeNameRule;
use amateescu\MagoDrupal\Linter\Rules\HookCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentBlankLineRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentPunctuationRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineVariableCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InsecureUnserializeRule;
use amateescu\MagoDrupal\Linter\Rules\InstallHookLocationRule;
use amateescu\MagoDrupal\Linter\Rules\LinkTextTranslatableRule;
use amateescu\MagoDrupal\Linter\Rules\LongDescriptionPunctuationRule;
use amateescu\MagoDrupal\Linter\Rules\MethodNameUnderscoreRule;
use amateescu\MagoDrupal\Linter\Rules\MethodVisibilityRule;
use amateescu\MagoDrupal\Linter\Rules\NullableParamTagRule;
use amateescu\MagoDrupal\Linter\Rules\NullCoalesceRule;
use amateescu\MagoDrupal\Linter\Rules\ParameterBlankLineRule;
use amateescu\MagoDrupal\Linter\Rules\PostStatementCommentRule;
use amateescu\MagoDrupal\Linter\Rules\PregSecurityRule;
use amateescu\MagoDrupal\Linter\Rules\PropertyNameRule;
use amateescu\MagoDrupal\Linter\Rules\PropertyPerStatementRule;
use amateescu\MagoDrupal\Linter\Rules\PropertyVisibilityRule;
use amateescu\MagoDrupal\Linter\Rules\RedundantReturnRule;
use amateescu\MagoDrupal\Linter\Rules\RedundantUseRule;
use amateescu\MagoDrupal\Linter\Rules\RemoteAddressRule;
use amateescu\MagoDrupal\Linter\Rules\RenderCallbackRule;
use amateescu\MagoDrupal\Linter\Rules\RequestSuperglobalRule;
use amateescu\MagoDrupal\Linter\Rules\ShortEchoTagRule;
use amateescu\MagoDrupal\Linter\Rules\ShortListRule;
use amateescu\MagoDrupal\Linter\Rules\StrictConfigSchemaRule;
use amateescu\MagoDrupal\Linter\Rules\SymfonyYamlParseRule;
use amateescu\MagoDrupal\Linter\Rules\TodoCommentRule;
use amateescu\MagoDrupal\Linter\Rules\TranslatableStringRule;
use amateescu\MagoDrupal\Linter\Rules\TranslatedExceptionRule;
use amateescu\MagoDrupal\Linter\Rules\TranslationInHookMenuRule;
use amateescu\MagoDrupal\Linter\Rules\TranslationInHookSchemaRule;
use amateescu\MagoDrupal\Linter\Rules\UnsilencedDeprecationRule;
use amateescu\MagoDrupal\Linter\Rules\UntranslatedOptionsRule;
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
     * The rules whose checks core's `phpcs.xml.dist` turns off or does not
     * run. With `--core`, they are off by default.
     */
    private const CORE_OFF = [
        'drupal/case-fall-through',
        'drupal/class-prefix',
        'drupal/const-prefix',
        'drupal/curl-ssl-verify',
        'drupal/form-alter-comment',
        'drupal/function-prefix',
        'drupal/global-constant',
        'drupal/hook-attribute-name',
        'drupal/inline-comment-blank-line',
        'drupal/inline-comment-punctuation',
        'drupal/long-description-punctuation',
        'drupal/method-name-underscore',
        'drupal/request-superglobal',
        'drupal/short-list',
        'drupal/strict-config-schema',
        'drupal/untranslated-options',
    ];

    /**
     * Builds the extension from the worker's arguments: `--core` when the
     * worker runs on Drupal core, and `--disable=<code>,<code>` for the rules
     * to turn off by default. `--disable` can be given more than once.
     *
     * @param array<mixed> $arguments
     */
    public static function fromArguments(array $arguments): Extension
    {
        $disabled = [];
        foreach (array_filter($arguments, is_string(...)) as $argument) {
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

        return self::create(core: in_array('--core', $arguments, strict: true), disabled: $disabled);
    }

    /**
     * @param bool $core Enables the rules that apply only to Drupal core, and
     *   turns off by default the rules that core's `phpcs.xml.dist` turns off.
     * @param list<string> $disabled The codes of the rules to turn off by
     *   default.
     *
     * @throws InvalidArgumentException When a code in $disabled names no rule.
     *
     * @mago-expect lint:no-boolean-flag-parameter
     */
    public static function create(bool $core = false, array $disabled = []): Extension
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
                new DrupalPlugin($core),
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
            new ByteOrderMarkRule(),
            new CaseBreakBlankLineRule(),
            new CaseFallThroughRule(),
            new CaseSemicolonRule(),
            new ClassCommentRule(),
            new ClassNameAcronymRule(),
            new ClassPrefixRule(),
            new CommentInExpressionRule(),
            new CommentLineLengthRule(),
            new ConstPrefixRule(),
            new ConstantPrefixRule(),
            new CurlSslVerifyRule(),
            new DefineNameRule(),
            new DeprecatedTagRule(),
            new DeprecationMessageRule(),
            new DiscouragedFunctionRule(),
            new DocCommentArraySyntaxRule(),
            new DocCommentRule(),
            new DocTypeNamespaceRule(),
            new ElseIfRule(),
            new EmptyPhpTagsRule(),
            new EmptyInstallHookRule(),
            new EmptySwitchRule(),
            new EnumCaseNameRule(),
            new ExpectedExceptionTagRule(),
            new FileCommentRule(),
            new FileEncodingRule(),
            new FileStartWhitespaceRule(),
            new FormAlterCommentRule(),
            new FullyQualifiedNameRule(),
            new FunctionCommentRule(),
            new FunctionPrefixRule(),
            new GenderNeutralCommentRule(),
            new GlobalConstantRule(),
            new GlobalFunctionRule(),
            new GlobalVariableRule(),
            new HookAttributeNameRule(),
            new HookCommentRule(),
            new InlineCommentRule(),
            new InlineCommentBlankLineRule(),
            new InlineCommentPunctuationRule(),
            new InlineVariableCommentRule(),
            new InsecureUnserializeRule(),
            new InstallHookLocationRule(),
            new LinkTextTranslatableRule(),
            new LongDescriptionPunctuationRule(),
            new MethodNameUnderscoreRule(),
            new MethodVisibilityRule(),
            new NullCoalesceRule(),
            new NullableParamTagRule(),
            new ParameterBlankLineRule(),
            new PostStatementCommentRule(),
            new PregSecurityRule(),
            new PropertyNameRule(),
            new PropertyPerStatementRule(),
            new PropertyVisibilityRule(),
            new RedundantReturnRule(),
            new RedundantUseRule(),
            new RemoteAddressRule(),
            new RenderCallbackRule(),
            new RequestSuperglobalRule(),
            new ShortEchoTagRule(),
            new ShortListRule(),
            new StrictConfigSchemaRule(),
            new SymfonyYamlParseRule(),
            new TodoCommentRule(),
            new TranslatableStringRule(),
            new TranslatedExceptionRule(),
            new TranslationInHookMenuRule(),
            new TranslationInHookSchemaRule(),
            new UnsilencedDeprecationRule(),
            new UntranslatedOptionsRule(),
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
