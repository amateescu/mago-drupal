<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal;

use amateescu\MagoDrupal\Analyzer\DrupalPlugin;
use amateescu\MagoDrupal\Linter\Rules\ClassCommentRule;
use amateescu\MagoDrupal\Linter\Rules\ConstantPrefixRule;
use amateescu\MagoDrupal\Linter\Rules\DeprecatedTagRule;
use amateescu\MagoDrupal\Linter\Rules\DeprecationMessageRule;
use amateescu\MagoDrupal\Linter\Rules\DocCommentArraySyntaxRule;
use amateescu\MagoDrupal\Linter\Rules\DocCommentRule;
use amateescu\MagoDrupal\Linter\Rules\DocTypeNamespaceRule;
use amateescu\MagoDrupal\Linter\Rules\EmptyInstallHookRule;
use amateescu\MagoDrupal\Linter\Rules\EnumCaseNameRule;
use amateescu\MagoDrupal\Linter\Rules\ExpectedExceptionTagRule;
use amateescu\MagoDrupal\Linter\Rules\FileCommentRule;
use amateescu\MagoDrupal\Linter\Rules\FunctionCommentRule;
use amateescu\MagoDrupal\Linter\Rules\GenderNeutralCommentRule;
use amateescu\MagoDrupal\Linter\Rules\GlobalFunctionRule;
use amateescu\MagoDrupal\Linter\Rules\GlobalVariableRule;
use amateescu\MagoDrupal\Linter\Rules\HookCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineVariableCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InstallHookLocationRule;
use amateescu\MagoDrupal\Linter\Rules\LinkTextTranslatableRule;
use amateescu\MagoDrupal\Linter\Rules\PostStatementCommentRule;
use amateescu\MagoDrupal\Linter\Rules\PregSecurityRule;
use amateescu\MagoDrupal\Linter\Rules\PropertyNameRule;
use amateescu\MagoDrupal\Linter\Rules\RedundantUseRule;
use amateescu\MagoDrupal\Linter\Rules\RemoteAddressRule;
use amateescu\MagoDrupal\Linter\Rules\TodoCommentRule;
use amateescu\MagoDrupal\Linter\Rules\TranslatableStringRule;
use amateescu\MagoDrupal\Linter\Rules\TranslatedExceptionRule;
use amateescu\MagoDrupal\Linter\Rules\TranslationInHookMenuRule;
use amateescu\MagoDrupal\Linter\Rules\TranslationInHookSchemaRule;
use amateescu\MagoDrupal\Linter\Rules\VariableCommentRule;
use amateescu\MagoDrupal\Linter\Rules\WatchdogMessageRule;
use amateescu\MagoDrupal\Linter\Rules\WeakHashRule;
use Mago\Sdk\Extension;

/**
 * Constructs the complete extension advertised by each worker process.
 *
 * This is the only registration API consumers need. Options belong here as
 * typed arguments, not as rule lists the caller has to assemble.
 *
 * @api
 */
final class DrupalExtension
{
    private const VERSION = '0.1.0';

    private function __construct() {}

    /**
     * @param bool $core Enables rules that only apply to Drupal core itself.
     */
    public static function create(bool $core = false): Extension
    {
        return new Extension(
            identifier: 'amateescu/mago-drupal',
            name: 'Drupal',
            version: self::VERSION,
            linterRules: [
                new ClassCommentRule(),
                new ConstantPrefixRule(),
                new DeprecatedTagRule(),
                new DeprecationMessageRule(),
                new DocCommentArraySyntaxRule(),
                new DocCommentRule(),
                new DocTypeNamespaceRule(),
                new EmptyInstallHookRule(),
                new EnumCaseNameRule(),
                new ExpectedExceptionTagRule(),
                new FileCommentRule(),
                new FunctionCommentRule(),
                new GenderNeutralCommentRule(),
                new GlobalFunctionRule(),
                new GlobalVariableRule(),
                new HookCommentRule(),
                new InlineCommentRule(),
                new InlineVariableCommentRule(),
                new InstallHookLocationRule(),
                new LinkTextTranslatableRule(),
                new PostStatementCommentRule(),
                new PregSecurityRule(),
                new PropertyNameRule(),
                new RedundantUseRule(),
                new RemoteAddressRule(),
                new TodoCommentRule(),
                new TranslatableStringRule(),
                new TranslatedExceptionRule(),
                new TranslationInHookMenuRule(),
                new TranslationInHookSchemaRule(),
                new VariableCommentRule(),
                new WatchdogMessageRule(),
                new WeakHashRule(),
            ],
            analyzerPlugins: [
                new DrupalPlugin($core),
            ],
        );
    }
}
