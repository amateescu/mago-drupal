<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal;

use amateescu\MagoDrupal\Analyzer\DrupalPlugin;
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
use amateescu\MagoDrupal\Linter\Rules\InlineCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InlineVariableCommentRule;
use amateescu\MagoDrupal\Linter\Rules\InsecureUnserializeRule;
use amateescu\MagoDrupal\Linter\Rules\InstallHookLocationRule;
use amateescu\MagoDrupal\Linter\Rules\LinkTextTranslatableRule;
use amateescu\MagoDrupal\Linter\Rules\MethodVisibilityRule;
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
use Mago\Sdk\Extension;

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
    private const VERSION = '0.1.0';

    private function __construct() {}

    /**
     * @param bool $core Enables the rules that apply only to Drupal core.
     * @param string|null $root Drupal document root, absolute or relative to
     *   the worker's cwd. Discovered from the cwd when null.
     */
    public static function create(bool $core = false, ?string $root = null): Extension
    {
        return new Extension(
            identifier: 'amateescu/mago-drupal',
            name: 'Drupal',
            version: self::VERSION,
            linterRules: [
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
                new InlineVariableCommentRule(),
                new InsecureUnserializeRule(),
                new InstallHookLocationRule(),
                new LinkTextTranslatableRule(),
                new MethodVisibilityRule(),
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
            ],
            analyzerPlugins: [
                new DrupalPlugin($core, $root),
                new PHPUnitPlugin(),
                new PHPStanIgnoresPlugin(),
            ],
        );
    }
}
