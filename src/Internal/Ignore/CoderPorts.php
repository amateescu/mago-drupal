<?php

declare(strict_types=1);

namespace amateescu\MagoDrupal\Internal\Ignore;

/**
 * The Coder codes that each rule and analyzer check of this extension ports,
 * from `tests/data/coder-map.json`, which the package doesn't ship.
 *
 * `just ports-gen` writes this file. Don't edit it by hand.
 *
 * @internal
 */
final class CoderPorts
{
    /**
     * The codes of Coder 9's `Drupal` and `DrupalPractice` standards, by the
     * rule or check that ports them.
     *
     * @var array<string, non-empty-list<string>>
     */
    public const PORTS = [
        'drupal/author-tag' => ['DrupalPractice.Commenting.AuthorTag.AuthorFound'],
        'drupal/byte-order-mark' => ['Generic.Files.ByteOrderMark.Found'],
        'drupal/case-break-blank-line' => ['Squiz.ControlStructures.SwitchDeclaration.SpacingAfterBreak'],
        'drupal/case-fall-through' => ['PSR2.ControlStructures.SwitchDeclaration.TerminatingComment'],
        'drupal/case-label-line' => [
            'Squiz.ControlStructures.SwitchDeclaration.ContentBeforeCase',
            'Squiz.ControlStructures.SwitchDeclaration.ContentBeforeDefault',
        ],
        'drupal/case-semicolon' => [
            'PSR2.ControlStructures.SwitchDeclaration.WrongOpenercase',
            'PSR2.ControlStructures.SwitchDeclaration.WrongOpenerdefault',
        ],
        'drupal/class-comment' => [
            'Drupal.Commenting.ClassComment.Short',
            'Drupal.Commenting.ClassComment.SpacingAfter',
            'Drupal.Commenting.ClassComment.WrongStyle',
        ],
        'drupal/class-name-acronym' => ['Drupal.NamingConventions.ValidClassName.NoUpperAcronyms'],
        'drupal/class-prefix' => ['DrupalPractice.General.ClassName.ClassPrefix'],
        'drupal/comment-in-expression' => [
            'Generic.Formatting.SpaceAfterCast.CommentFound',
            'Generic.WhiteSpace.LanguageConstructSpacing.IncorrectYieldFromWithComment',
        ],
        'drupal/comment-line-length' => ['Drupal.Files.LineLength.TooLong'],
        'drupal/const-prefix' => ['Drupal.Semantics.ConstantName.ConstConstantStart'],
        'drupal/constant-prefix' => ['Drupal.Semantics.ConstantName.ConstantStart'],
        'drupal/curl-ssl-verify' => ['DrupalPractice.FunctionCalls.CurlSslVerifier.SslPeerVerificationDisabled'],
        'drupal/define-name' => ['Generic.NamingConventions.UpperCaseConstantName.ConstantNotUpperCase'],
        'drupal/deprecated-tag' => [
            'Drupal.Commenting.Deprecated.DeprecatedMissingSeeTag',
            'Drupal.Commenting.Deprecated.DeprecatedPeriodAfterSeeUrl',
            'Drupal.Commenting.Deprecated.DeprecatedVersionFormat',
            'Drupal.Commenting.Deprecated.DeprecatedWrongSeeUrlFormat',
            'Drupal.Commenting.Deprecated.IncorrectTextLayout',
            'Drupal.Commenting.Deprecated.MissingExtraInfo',
        ],
        'drupal/deprecation-message' => [
            'Drupal.Semantics.FunctionTriggerError.TriggerErrorPeriodAfterSeeUrl',
            'Drupal.Semantics.FunctionTriggerError.TriggerErrorSeeUrlFormat',
            'Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutRelaxed',
            'Drupal.Semantics.FunctionTriggerError.TriggerErrorTextLayoutStrict',
            'Drupal.Semantics.FunctionTriggerError.TriggerErrorVersion',
        ],
        'drupal/discouraged-function' => ['Drupal.Functions.DiscouragedFunctions.Discouraged'],
        'drupal/doc-comment' => [
            'Drupal.Commenting.DocComment.ContentAfterOpen',
            'Drupal.Commenting.DocComment.InheritDocWithoutBraces',
            'Drupal.Commenting.DocComment.ShortStartSpace',
            'Drupal.Commenting.DocComment.SpacingAfter',
            'Drupal.Commenting.DocComment.SpacingBeforeShort',
            'Drupal.Commenting.DocComment.SpacingBetween',
            'Drupal.Commenting.DocComment.TagValueIndent',
            'Drupal.Commenting.DocComment.WrongEnd',
            'Drupal.Commenting.DocCommentAlignment.NoSpaceAfterStar',
            'Drupal.Commenting.DocCommentAlignment.SpaceAfterStar',
            'SlevomatCodingStandard.Commenting.ForbiddenComments.CommentForbidden',
        ],
        'drupal/doc-comment-array-syntax' => ['Drupal.Commenting.DocCommentLongArraySyntax.DocLongArray'],
        'drupal/doc-see-tag' => [
            'Drupal.Commenting.FunctionComment.EmptySees',
            'Drupal.Commenting.FunctionComment.SeeAdditionalText',
            'Drupal.Commenting.FunctionComment.SeePunctuation',
            'Drupal.Commenting.VariableComment.EmptySees',
        ],
        'drupal/doc-sentence' => [
            'Drupal.Commenting.DocComment.LongNotCapital',
            'Drupal.Commenting.DocComment.ShortFullStop',
            'Drupal.Commenting.DocComment.ShortNotCapital',
            'Drupal.Commenting.DocComment.ShortSingleLine',
            'Drupal.Commenting.FunctionComment.ParamCommentFullStop',
            'Drupal.Commenting.FunctionComment.ParamCommentNotCapital',
            'Drupal.Commenting.FunctionComment.ThrowsNoFullStop',
            'Drupal.Commenting.FunctionComment.ThrowsNotCapital',
        ],
        'drupal/doc-tag-descriptions' => [
            'Drupal.Commenting.FunctionComment.MissingParamComment',
            'Drupal.Commenting.FunctionComment.MissingReturnComment',
            'Drupal.Commenting.FunctionComment.ParamMissingDefinition',
            'Drupal.Commenting.FunctionComment.ParamNameNoMatch',
        ],
        'drupal/doc-tag-groups' => [
            'Drupal.Commenting.DocComment.ParamGroup',
            'Drupal.Commenting.DocComment.ParamNotFirst',
            'Drupal.Commenting.DocComment.SpacingAfterTagGroup',
            'Drupal.Commenting.DocComment.SpacingBeforeTags',
            'Drupal.Commenting.DocComment.TagGroupSpacing',
            'Drupal.Commenting.DocComment.TagsNotGrouped',
        ],
        'drupal/doc-tag-layout' => [
            'Drupal.Commenting.FunctionComment.ParamCommentIndentation',
            'Drupal.Commenting.FunctionComment.ParamCommentNewLine',
            'Drupal.Commenting.FunctionComment.ParamNameDot',
            'Drupal.Commenting.FunctionComment.ParamTypeSpaces',
            'Drupal.Commenting.FunctionComment.ReturnCommentIndentation',
            'Drupal.Commenting.FunctionComment.ReturnTypeSpaces',
            'Drupal.Commenting.FunctionComment.ReturnVarName',
            'Drupal.Commenting.FunctionComment.SpacingAfterParamType',
            'Drupal.Commenting.FunctionComment.ThrowsComment',
            'Drupal.Commenting.FunctionComment.ThrowsCommentIndentation',
        ],
        'drupal/doc-tag-types' => [
            'Drupal.Commenting.FunctionComment.DuplicateReturn',
            'Drupal.Commenting.FunctionComment.MissingParamName',
            'Drupal.Commenting.FunctionComment.MissingParamType',
            'Drupal.Commenting.FunctionComment.MissingReturnType',
        ],
        'drupal/doc-type-name' => [
            'Drupal.Commenting.FunctionComment.IncorrectParamVarName',
            'Drupal.Commenting.FunctionComment.InvalidReturn',
            'Drupal.Commenting.VariableComment.IncorrectVarType',
        ],
        'drupal/doc-type-namespace' => ['SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse'],
        'drupal/else-if' => ['PSR2.ControlStructures.ElseIfDeclaration.NotAllowed'],
        'drupal/empty-install-hook' => ['Drupal.Semantics.EmptyInstall.EmptyInstall'],
        'drupal/empty-php-tags' => ['Generic.CodeAnalysis.EmptyPHPStatement.EmptyPHPOpenCloseTagsDetected'],
        'drupal/empty-switch' => ['Squiz.ControlStructures.SwitchDeclaration.MissingCase'],
        'drupal/enum-case-name' => [
            'Drupal.NamingConventions.ValidEnumCase.NoUnderscores',
            'Drupal.NamingConventions.ValidEnumCase.NoUpperAcronyms',
            'Drupal.NamingConventions.ValidEnumCase.StartWithCapital',
        ],
        'drupal/escaped-placeholder' => ['DrupalPractice.FunctionCalls.TCheckPlain.CheckPlain'],
        'drupal/expected-exception-tag' => ['DrupalPractice.Commenting.ExpectedException.TagFound'],
        'drupal/explicit-nullable-param' => [
            'SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue.NullabilityTypeMissing',
        ],
        'drupal/file-comment' => [
            'Drupal.Commenting.FileComment.FileTag',
            'Drupal.Commenting.FileComment.NamespaceNoFileDoc',
            'Drupal.Commenting.FileComment.SpacingAfterComment',
            'Drupal.Commenting.FileComment.WrongStyle',
            'SlevomatCodingStandard.TypeHints.DeclareStrictTypes.IncorrectWhitespaceBeforeDeclare',
        ],
        'drupal/file-encoding' => ['Drupal.Files.FileEncoding.InvalidEncoding'],
        'drupal/file-start-whitespace' => ['Squiz.WhiteSpace.SuperfluousWhitespace.StartFile'],
        'drupal/form-alter-comment' => ['DrupalPractice.FunctionDefinitions.FormAlterDoc.Different'],
        'drupal/fully-qualified-name' => ['Drupal.Classes.FullyQualifiedNamespace.UseStatementMissing'],
        'drupal/function-blank-line' => ['Squiz.WhiteSpace.FunctionSpacing.Before'],
        'drupal/function-comment' => [
            'Drupal.Commenting.FunctionComment.SpacingAfter',
            'Drupal.Commenting.FunctionComment.WrongStyle',
        ],
        'drupal/function-name' => ['Drupal.NamingConventions.ValidFunctionName.InvalidName'],
        'drupal/function-prefix' => ['Drupal.NamingConventions.ValidFunctionName.InvalidPrefix'],
        'drupal/gender-neutral-comment' => ['Drupal.Commenting.GenderNeutralComment.GenderNeutral'],
        'drupal/global-constant' => [
            'DrupalPractice.Constants.GlobalConstant.GlobalConstant',
            'DrupalPractice.Constants.GlobalDefine.GlobalConstant',
        ],
        'drupal/global-drupal-call' => ['DrupalPractice.Objects.GlobalDrupal.GlobalDrupal'],
        'drupal/global-function' => ['DrupalPractice.Objects.GlobalFunction.GlobalFunction'],
        'drupal/global-variable' => ['Drupal.NamingConventions.ValidGlobal.GlobalUnderScore'],
        'drupal/hook-attribute-name' => ['Drupal.Attributes.ValidHookName.HookPrefix'],
        'drupal/hook-comment' => [
            'Drupal.Commenting.HookComment.HookCommentFormat',
            'Drupal.Commenting.HookComment.HookParamDoc',
            'Drupal.Commenting.HookComment.HookRepeat',
            'Drupal.Commenting.HookComment.HookReturnDoc',
        ],
        'drupal/info-auto-added-keys' => [
            'Drupal.InfoFiles.AutoAddedKeys.Project',
            'Drupal.InfoFiles.AutoAddedKeys.Timestamp',
            'Drupal.InfoFiles.AutoAddedKeys.Version',
        ],
        'drupal/info-core-version-requirement' => [
            'DrupalPractice.InfoFiles.CoreVersionRequirement.CoreVersionRequirement',
        ],
        'drupal/info-dependencies-array' => ['Drupal.InfoFiles.DependenciesArray.Dependencies'],
        'drupal/info-description' => [
            'DrupalPractice.InfoFiles.Description.Empty',
            'DrupalPractice.InfoFiles.Description.Missing',
        ],
        'drupal/info-namespaced-dependency' => ['DrupalPractice.InfoFiles.NamespacedDependency.NonNamespaced'],
        'drupal/inline-comment' => [
            'Drupal.Commenting.InlineComment.DocBlock',
            'Drupal.Commenting.InlineComment.NoSpaceBefore',
            'Drupal.Commenting.InlineComment.NotCapital',
            'Drupal.Commenting.InlineComment.SpacingBefore',
            'Drupal.Commenting.InlineComment.TabBefore',
            'Drupal.Commenting.InlineComment.WrongStyle',
        ],
        'drupal/inline-comment-blank-line' => [
            'Drupal.Commenting.InlineComment.SpacingAfter',
            'DrupalPractice.Commenting.CommentEmptyLine.SpacingAfter',
        ],
        'drupal/inline-comment-punctuation' => ['Drupal.Commenting.InlineComment.InvalidEndChar'],
        'drupal/inline-variable-comment' => [
            'Drupal.Commenting.InlineVariableComment.VarInline',
            'Drupal.Commenting.InlineVariableComment.VarInlineOrder',
        ],
        'drupal/insecure-unserialize' => ['DrupalPractice.FunctionCalls.InsecureUnserialize.InsecureUnserialize'],
        'drupal/install-hook-location' => ['Drupal.Semantics.InstallHooks.InstallHook'],
        'drupal/link-text-translatable' => ['Drupal.Semantics.LStringTranslatable.LArg'],
        'drupal/literal-constant-case' => ['Generic.PHP.UpperCaseConstant.Found'],
        'drupal/long-description-punctuation' => ['Drupal.Commenting.DocComment.LongFullStop'],
        'drupal/method-name' => [
            'Drupal.NamingConventions.ValidFunctionName.NotCamelCaps',
            'Drupal.NamingConventions.ValidFunctionName.ScopeNotCamelCaps',
        ],
        'drupal/method-name-underscore' => [
            'Drupal.NamingConventions.ValidFunctionName.MethodDoubleUnderscore',
            'PSR2.Methods.MethodDeclaration.Underscore',
        ],
        'drupal/method-visibility' => ['Drupal.Scope.MethodScope.Missing'],
        'drupal/missing-class-comment' => ['Drupal.Commenting.ClassComment.Missing'],
        'drupal/missing-file-comment' => ['Drupal.Commenting.FileComment.Missing'],
        'drupal/missing-function-comment' => ['Drupal.Commenting.FunctionComment.Missing'],
        'drupal/missing-property-comment' => ['Drupal.Commenting.VariableComment.Missing'],
        'drupal/missing-summary' => ['Drupal.Commenting.DocComment.MissingShort'],
        'drupal/null-coalesce' => [
            'SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator.NullCoalesceOperatorNotUsed',
        ],
        'drupal/old-style-constructor' => ['Generic.NamingConventions.ConstructorName.OldStyle'],
        'drupal/parameter-blank-line' => ['Drupal.Functions.MultiLineFunctionDeclaration.EmptyLine'],
        'drupal/post-statement-comment' => [
            'Drupal.Classes.ClassDeclaration.CloseBraceSameLine',
            'Drupal.Classes.ClassDeclaration.ContentAfterBrace',
            'Drupal.Commenting.PostStatementComment.Found',
        ],
        'drupal/preg-security' => ['Drupal.Semantics.PregSecurity.PregEFlag'],
        'drupal/property-name' => [
            'Drupal.NamingConventions.ValidVariableName.LowerCamelName',
            'PSR2.Classes.PropertyDeclaration.Underscore',
        ],
        'drupal/property-per-statement' => ['PSR2.Classes.PropertyDeclaration.Multiple'],
        'drupal/property-visibility' => [
            'Drupal.Classes.PropertyDeclaration.VarUsed',
            'PSR2.Classes.PropertyDeclaration.ScopeMissing',
        ],
        'drupal/redundant-return' => ['Squiz.PHP.NonExecutableCode.ReturnNotRequired'],
        'drupal/redundant-use' => ['Drupal.Classes.UseGlobalClass.RedundantUseStatement'],
        'drupal/remote-address' => ['Drupal.Semantics.RemoteAddress.RemoteAddress'],
        'drupal/request-superglobal' => [
            'DrupalPractice.Variables.GetRequestData.SuperglobalAccessed',
            'DrupalPractice.Variables.GetRequestData.SuperglobalAccessedWithVar',
        ],
        'drupal/routing-access' => [
            'DrupalPractice.Yaml.RoutingAccess.OpenCallback',
            'DrupalPractice.Yaml.RoutingAccess.PermissionFound',
        ],
        'drupal/short-echo-tag' => ['Generic.PHP.DisallowShortOpenTag.EchoFound'],
        'drupal/short-list' => ['SlevomatCodingStandard.PHP.ShortList.LongListUsed'],
        'drupal/static-entity-load' => ['DrupalPractice.Objects.GlobalClass.GlobalClass'],
        'drupal/strict-config-schema' => ['DrupalPractice.Objects.StrictSchemaDisabled.StrictConfigSchema'],
        'drupal/t-in-hook-menu' => ['Drupal.Semantics.TInHookMenu.TFound'],
        'drupal/t-in-hook-schema' => ['Drupal.Semantics.TInHookSchema.TFound'],
        'drupal/tab-character' => [
            'Generic.WhiteSpace.DisallowTabIndent.NonIndentTabsUsed',
            'Generic.WhiteSpace.DisallowTabIndent.TabsUsed',
        ],
        'drupal/todo-comment' => ['Drupal.Commenting.TodoComment.TodoFormat'],
        'drupal/translatable-literal' => [
            'Drupal.Semantics.FunctionT.ConcatString',
            'Drupal.Semantics.FunctionT.NotLiteralString',
        ],
        'drupal/translatable-string' => [
            'Drupal.Semantics.FunctionT.BackslashDoubleQuote',
            'Drupal.Semantics.FunctionT.BackslashSingleQuote',
            'Drupal.Semantics.FunctionT.Concat',
            'Drupal.Semantics.FunctionT.EmptyString',
            'Drupal.Semantics.FunctionT.EmptyT',
            'Drupal.Semantics.FunctionT.WhiteSpace',
        ],
        'drupal/translated-exception' => ['DrupalPractice.General.ExceptionT.ExceptionT'],
        'drupal/unsilenced-deprecation' => ['Drupal.Semantics.UnsilencedDeprecation.UnsilencedDeprecation'],
        'drupal/untranslated-description' => ['DrupalPractice.General.DescriptionT.DescriptionT'],
        'drupal/untranslated-message' => [
            'DrupalPractice.FunctionCalls.FormErrorT.ErrorMessage',
            'DrupalPractice.FunctionCalls.MessageT.ErrorMessage',
        ],
        'drupal/untranslated-options' => ['DrupalPractice.General.OptionsT.OptionsValue'],
        'drupal/unused-variable' => ['VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable'],
        'drupal/use-leading-backslash' => [
            'SlevomatCodingStandard.Namespaces.UseDoesNotStartWithBackslash.UseStartsWithBackslash',
        ],
        'drupal/variable-comment' => [
            'Drupal.Commenting.VariableComment.DuplicateVar',
            'Drupal.Commenting.VariableComment.EmptyVar',
            'Drupal.Commenting.VariableComment.InlineVariableName',
            'Drupal.Commenting.VariableComment.MissingVar',
            'Drupal.Commenting.VariableComment.VarOrder',
            'Drupal.Commenting.VariableComment.WrongStyle',
        ],
        'drupal/variable-name' => ['Drupal.NamingConventions.ValidVariableName.LowerStart'],
        'drupal/variable-redeclaration' => ['VariableAnalysis.CodeAnalysis.VariableAnalysis.VariableRedeclaration'],
        'drupal/watchdog-message' => [
            'Drupal.Semantics.FunctionWatchdog.Concat',
            'Drupal.Semantics.FunctionWatchdog.WatchdogArgument',
            'Drupal.Semantics.FunctionWatchdog.WatchdogT',
        ],
    ];

    private function __construct() {}
}
