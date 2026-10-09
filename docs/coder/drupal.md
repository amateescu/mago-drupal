# Drupal standard

The sniffs of Coder's own `Drupal` standard, by category. The sniffs that it takes from other
standards are on [Sniffs from other standards](other-standards.md).

<!-- docs-gen:coder-drupal -->

## Arrays

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Arrays.Array` | `mago format` | The drupal preset writes the trailing comma, the indentation and the line breaks of a long array. A few nested layouts still differ. |

## Attributes

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Attributes.ValidHookName` | [`drupal/hook-attribute-name`](../rules/naming-and-imports.md#drupalhook-attribute-name) |  |

## Classes

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Classes.ClassDeclaration` | `BraceOnNewLine`, `CloseBraceAfterBody`, `ExtendsIndentation`, `ExtendsInterfaceSameLine`, `ExtendsLine`, `FirstExtendsInterfaceSameLine`, `FirstInterfaceSameLine`, `ImplementsLine`, `InterfaceSameLine`, `InterfaceWrongIndent`, `MissingBrace`, `NewlineBeforeKeyword`, `NoSpaceBeforeName`, `OpenBraceNewLine`, `OpenBraceNotAlone`, `OpenBraceWrongLine`, `SpaceAfterKeyword`, `SpaceAfterName`, `SpaceBeforeBrace`, `SpaceBeforeComma`, `SpaceBeforeExtends`, `SpaceBeforeImplements`, `SpaceBeforeKeyword`, `SpaceBeforeName`: `mago format`<br>`CloseBraceSameLine`: Nothing<br>`ContentAfterBrace`: `mago format`, partly | `CloseBraceSameLine`: A comment after the closing brace of a class, such as `} // end class`. The formatter keeps it and nothing reports it. phpcbf has no fix for it either. `ContentAfterBrace`: The formatter keeps a comment that follows the opening brace on the brace line, such as `class Foo { // Comment.`, which Coder reports. |
| `Drupal.Classes.FullyQualifiedNamespace` | [`drupal/fully-qualified-name`](../rules/naming-and-imports.md#drupalfully-qualified-name) |  |
| `Drupal.Classes.PropertyDeclaration` | [`drupal/property-visibility`](../rules/statements.md#drupalproperty-visibility) |  |
| `Drupal.Classes.UseGlobalClass` | [`drupal/redundant-use`](../rules/naming-and-imports.md#drupalredundant-use) |  |

## Commenting

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Commenting.ClassComment` | [`drupal/class-comment`](../rules/docblock-structure.md#drupalclass-comment) |  |
| `Drupal.Commenting.Deprecated` | [`drupal/deprecated-tag`](../rules/docblock-structure.md#drupaldeprecated-tag) |  |
| `Drupal.Commenting.DocComment` | `ContentAfterOpen`, `InheritDocWithoutBraces`, `LongNotCapital`, `ParamGroup`, `ParamNotFirst`, `ShortFullStop`, `ShortNotCapital`, `ShortSingleLine`, `ShortStartSpace`, `SpacingAfter`, `SpacingAfterTagGroup`, `SpacingBeforeShort`, `SpacingBeforeTags`, `SpacingBetween`, `TagGroupSpacing`, `TagValueIndent`, `TagsNotGrouped`, `WrongEnd`: [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment)<br>`Empty`: [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment), Mago `no-empty-comment`<br>`LongFullStop`: [`drupal/long-description-punctuation`](../rules/comment-text.md#drupallong-description-punctuation)<br>`MissingShort`: [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment), partly |  |
| `Drupal.Commenting.DocCommentAlignment` | `NoSpaceAfterStar`, `SpaceAfterStar`: [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment)<br>`SpaceBeforeStar`: `mago format` |  |
| `Drupal.Commenting.DocCommentLongArraySyntax` | [`drupal/doc-comment-array-syntax`](../rules/comment-text.md#drupaldoc-comment-array-syntax) |  |
| `Drupal.Commenting.DocCommentStar` | `mago format` | The formatter puts the star back on a docblock line that has none. |
| `Drupal.Commenting.FileComment` | `FileTag`, `Missing`: [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment)<br>`NamespaceNoFileDoc`, `TemplateSpacingAfterComment`: Nothing<br>`SpacingAfterComment`: [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), `mago format`<br>`WrongStyle`: [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), partly | `NamespaceNoFileDoc`: A file docblock above the namespace of a class file. Not ported yet: the rule does not read `.php` files. `TemplateSpacingAfterComment`: The shape of a Drupal 7 `.tpl.php` template. Not ported. |
| `Drupal.Commenting.FunctionComment` | `DuplicateReturn`, `EmptySees`, `MissingParamComment`, `MissingParamName`, `MissingReturnComment`, `ParamCommentNotCapital`, `ParamTypeSpaces`, `ReturnTypeSpaces`, `SeeAdditionalText`, `ThrowsComment`, `ThrowsCommentIndentation`, `ThrowsNoFullStop`, `ThrowsNotCapital`: [`drupal/function-comment`](../rules/docblock-structure.md#drupalfunction-comment)<br>`ExtraParamComment`, `ParamNameNoCaseMatch`: `mago analyze` (`invalid-param-tag`)<br>`IncorrectParamVarName`, `InvalidReturn`, `Missing`, `MissingParamType`, `ParamCommentFullStop`, `ParamCommentIndentation`, `ParamCommentNewLine`, `ParamMissingDefinition`, `ParamNameDot`, `ReturnCommentIndentation`, `ReturnVarName`, `SeePunctuation`, `SpacingAfter`, `SpacingAfterParamType`, `WrongStyle`: [`drupal/function-comment`](../rules/docblock-structure.md#drupalfunction-comment), `mago analyze`<br>`InvalidNoReturn`: `mago analyze` (`missing-return-statement`)<br>`InvalidReturnNotVoid`, `InvalidReturnVoid`: `mago analyze` (`invalid-return-statement`)<br>`InvalidThrows`: Mago `valid-docblock`<br>`MissingReturnType`: [`drupal/function-comment`](../rules/docblock-structure.md#drupalfunction-comment), Mago `valid-docblock`<br>`ParamNameNoMatch`: `mago analyze` (`invalid-param-tag`), partly | `InvalidNoReturn`: Reported on the function, not on the `@return` tag. `InvalidReturnVoid`: Reported on the `return` statement, not on the `@return void` tag. `InvalidThrows`: A generic docblock parse error. `ParamNameNoMatch`: The analyzer reports a `@param` tag that names no parameter. It does not report a duplicate tag or tags in a different order than the parameters, which Coder reports. |
| `Drupal.Commenting.GenderNeutralComment` | [`drupal/gender-neutral-comment`](../rules/comment-text.md#drupalgender-neutral-comment) |  |
| `Drupal.Commenting.HookComment` | [`drupal/hook-comment`](../rules/docblock-structure.md#drupalhook-comment) |  |
| `Drupal.Commenting.InlineComment` | `DocBlock`, `NoSpaceBefore`, `NotCapital`, `TabBefore`: [`drupal/inline-comment`](../rules/comment-text.md#drupalinline-comment)<br>`Empty`: Mago `no-empty-comment`<br>`InvalidEndChar`: [`drupal/inline-comment-punctuation`](../rules/comment-text.md#drupalinline-comment-punctuation), partly<br>`SpacingAfter`: [`drupal/inline-comment-blank-line`](../rules/comment-text.md#drupalinline-comment-blank-line), `mago format`, partly<br>`SpacingAfterAtFunctionEnd`: `mago format`<br>`SpacingBefore`: [`drupal/inline-comment`](../rules/comment-text.md#drupalinline-comment), partly<br>`WrongStyle`: `mago format`, [`drupal/inline-comment`](../rules/comment-text.md#drupalinline-comment) | `Empty`: Mago's rule, at note level. |
| `Drupal.Commenting.InlineVariableComment` | [`drupal/inline-variable-comment`](../rules/docblock-structure.md#drupalinline-variable-comment) |  |
| `Drupal.Commenting.PostStatementComment` | [`drupal/post-statement-comment`](../rules/comment-text.md#drupalpost-statement-comment) |  |
| `Drupal.Commenting.TodoComment` | [`drupal/todo-comment`](../rules/comment-text.md#drupaltodo-comment) |  |
| `Drupal.Commenting.VariableComment` | `DuplicateVar`, `EmptySees`, `EmptyVar`, `InlineVariableName`, `Missing`, `MissingVar`, `VarOrder`, `WrongStyle`: [`drupal/variable-comment`](../rules/docblock-structure.md#drupalvariable-comment)<br>`IncorrectVarType`: [`drupal/variable-comment`](../rules/docblock-structure.md#drupalvariable-comment), partly |  |

## ControlStructures

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.ControlStructures.ControlSignature` | `NewlineAfterCloseBrace`, `SpaceAfterCloseBrace`, `SpaceAfterCloseParenthesis`, `SpaceAfterKeyword`, `SpaceBeforeSemicolon`: `mago format`<br>`NewlineAfterOpenBrace`: `mago format`, partly | `NewlineAfterOpenBrace`: The formatter writes a `match` with one arm on one line, and Coder reports that line. |

## Files

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Files.EndFileNewline` | `mago format` | PHP files only. Coder also checks the last line of `.txt`, `.md` and `.yml` files, which Mago does not read. |
| `Drupal.Files.FileEncoding` | [`drupal/file-encoding`](../rules/files-and-tags.md#drupalfile-encoding) |  |
| `Drupal.Files.LineLength` | [`drupal/comment-line-length`](../rules/comment-text.md#drupalcomment-line-length) |  |
| `Drupal.Files.TxtFileLineLength` | phpcs | Checks `.txt` and `.md` files. Mago reads only PHP. |

## Formatting

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Formatting.MultiLineAssignment` | `mago format` |  |
| `Drupal.Formatting.MultipleStatementAlignment` | `mago format` |  |
| `Drupal.Formatting.SpaceInlineIf` | `mago format` |  |
| `Drupal.Formatting.SpaceUnaryOperator` | `mago format` | The formatter can break a long `!(...)` condition after the `!`, which Coder then reports. |

## Functions

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Functions.DiscouragedFunctions` | [`drupal/discouraged-function`](../rules/phpstan-drupal.md#drupaldiscouraged-function), Mago `no-eval` |  |
| `Drupal.Functions.MultiLineFunctionDeclaration` | `BraceOnNewLine`, `CloseBracketLine`, `CloseBracketNewLine`, `Indent`, `MissingTrailingComma`, `NewlineBeforeOpenBrace`, `SpaceAfterFunction`, `SpaceAfterUse`, `SpaceBeforeBrace`, `SpaceBeforeOpenBrace`, `SpaceBeforeOpenParen`, `SpaceBeforeSemicolon`, `SpaceBeforeUse`, `UseCloseBracketLine`: `mago format`<br>`EmptyLine`: [`drupal/parameter-blank-line`](../rules/statements.md#drupalparameter-blank-line) |  |

## InfoFiles

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.InfoFiles.AutoAddedKeys` | phpcs | Checks `.info.yml` files. Mago cannot report an issue in a YAML file. |
| `Drupal.InfoFiles.ClassFiles` | phpcs |  |
| `Drupal.InfoFiles.DependenciesArray` | phpcs |  |
| `Drupal.InfoFiles.DuplicateEntry` | phpcs |  |
| `Drupal.InfoFiles.Required` | phpcs |  |

## NamingConventions

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.NamingConventions.ValidClassName` | `NoUnderscores`, `StartWithCapital`: Mago `class-name`, Mago `interface-name`, Mago `trait-name`, Mago `enum-name`<br>`NoUpperAcronyms`: [`drupal/class-name-acronym`](../rules/naming-and-imports.md#drupalclass-name-acronym) |  |
| `Drupal.NamingConventions.ValidEnumCase` | [`drupal/enum-case-name`](../rules/naming-and-imports.md#drupalenum-case-name) |  |
| `Drupal.NamingConventions.ValidFunctionName` | `InvalidName`: Mago `function-name`, partly<br>`InvalidPrefix`: [`drupal/function-prefix`](../rules/procedural-files.md#drupalfunction-prefix)<br>`MethodDoubleUnderscore`: [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore)<br>`NotCamelCaps`, `ScopeNotCamelCaps`: Mago `method-name` (off by default) | Core does not run the sniff. Mago's `method-name` rule would cover the method codes, but it is off by default and the README does not turn it on. `InvalidName`: Coder only wants a lower-case name. Mago's `function-name` wants snake case, so it also reports a name with a leading, trailing or doubled underscore, such as `_mymodule_helper()`. With `either = true` it accepts a camelCase name, which Coder reports. `NotCamelCaps`: Mago's `method-name` rule, off by default. The README does not turn it on. `ScopeNotCamelCaps`: Mago's `method-name` rule, off by default. The README does not turn it on. |
| `Drupal.NamingConventions.ValidGlobal` | [`drupal/global-variable`](../rules/procedural-files.md#drupalglobal-variable) |  |
| `Drupal.NamingConventions.ValidVariableName` | `LowerCamelName`: [`drupal/property-name`](../rules/naming-and-imports.md#drupalproperty-name)<br>`LowerStart`: Mago `variable-name` (off by default) | `LowerStart`: Mago's `variable-name` rule, off by default. The README does not turn it on. It checks for camel or snake case, not for a lower-case first letter, and skips `foreach`, `catch`, `static` and `global` variables. |

## Scope

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Scope.MethodScope` | [`drupal/method-visibility`](../rules/statements.md#drupalmethod-visibility) |  |

## Semantics

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Semantics.ConstantName` | `ConstConstantStart`: [`drupal/const-prefix`](../rules/procedural-files.md#drupalconst-prefix)<br>`ConstantStart`: [`drupal/constant-prefix`](../rules/procedural-files.md#drupalconstant-prefix) |  |
| `Drupal.Semantics.EmptyInstall` | [`drupal/empty-install-hook`](../rules/procedural-files.md#drupalempty-install-hook) |  |
| `Drupal.Semantics.FunctionAlias` | Mago `no-alias-function` | Mago's rule, on by default, with a fix. |
| `Drupal.Semantics.FunctionT` | `BackslashDoubleQuote`: [`drupal/translatable-string`](../rules/right-api.md#drupaltranslatable-string), `mago format`<br>`BackslashSingleQuote`, `Concat`, `ConcatString`, `EmptyString`, `EmptyT`, `NotLiteralString`, `WhiteSpace`: [`drupal/translatable-string`](../rules/right-api.md#drupaltranslatable-string) |  |
| `Drupal.Semantics.FunctionTriggerError` | [`drupal/deprecation-message`](../rules/right-api.md#drupaldeprecation-message) |  |
| `Drupal.Semantics.FunctionWatchdog` | [`drupal/watchdog-message`](../rules/drupal-7.md#drupalwatchdog-message) |  |
| `Drupal.Semantics.InstallHooks` | [`drupal/install-hook-location`](../rules/procedural-files.md#drupalinstall-hook-location) |  |
| `Drupal.Semantics.LStringTranslatable` | [`drupal/link-text-translatable`](../rules/drupal-7.md#drupallink-text-translatable) |  |
| `Drupal.Semantics.PregSecurity` | [`drupal/preg-security`](../rules/bugs-and-security.md#drupalpreg-security) |  |
| `Drupal.Semantics.RemoteAddress` | [`drupal/remote-address`](../rules/bugs-and-security.md#drupalremote-address) |  |
| `Drupal.Semantics.TInHookMenu` | [`drupal/t-in-hook-menu`](../rules/drupal-7.md#drupalt-in-hook-menu) |  |
| `Drupal.Semantics.TInHookSchema` | [`drupal/t-in-hook-schema`](../rules/procedural-files.md#drupalt-in-hook-schema) |  |
| `Drupal.Semantics.UnsilencedDeprecation` | [`drupal/unsilenced-deprecation`](../rules/right-api.md#drupalunsilenced-deprecation) |  |

## WhiteSpace

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.WhiteSpace.CloseBracketSpacing` | `mago format` |  |
| `Drupal.WhiteSpace.Comma` | `mago format` |  |
| `Drupal.WhiteSpace.EmptyLines` | `mago format` |  |
| `Drupal.WhiteSpace.ObjectOperatorIndent` | `mago format` |  |
| `Drupal.WhiteSpace.ObjectOperatorSpacing` | `mago format` |  |
| `Drupal.WhiteSpace.OpenBracketSpacing` | `mago format` |  |
| `Drupal.WhiteSpace.OpenTagNewline` | `mago format` |  |
| `Drupal.WhiteSpace.ScopeClosingBrace` | `mago format` |  |
| `Drupal.WhiteSpace.ScopeIndent` | `mago format` | The formatter writes the indentation. A few layouts it writes differ from what Coder expects. |

<!-- /docs-gen -->
