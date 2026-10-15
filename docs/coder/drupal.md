# Drupal standard

The sniffs of Coder's own `Drupal` standard, by category. The sniffs taken from other standards are
on [Sniffs from other standards](other-standards.md).

<!-- docs-gen:coder-drupal -->

## Arrays

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Arrays.Array` | `ArrayClosingIndentation`, `ArrayIndentation`: `mago format`, partly<br>`CommaLastItem`, `LongLineDeclaration`: `mago format` | The drupal preset writes the trailing comma, the indentation and the line breaks of a long array. `ArrayClosingIndentation`: An array that opens on a continuation line gets the indent of the statement's first line. Coder counts from the line the array opens on, and reports its items and closing bracket. `ArrayIndentation`: When the value after `=>` breaks over lines, such as a long condition, the formatter writes the next lines at the key's indent. Coder wants them one level deeper. The formatter also writes the broken `&&` or `\|\|` condition of a ternary in an array element at the element's indent, and breaks an array at a block comment between its elements, and Coder reports both. |

## Attributes

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Attributes.ValidHookName` | [`drupal/hook-attribute-name`](../rules/naming-and-imports.md#drupalhook-attribute-name) |  |

## Classes

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Classes.ClassDeclaration` | `BraceOnNewLine`, `CloseBraceAfterBody`, `ExtendsIndentation`, `ExtendsInterfaceSameLine`, `ExtendsLine`, `FirstExtendsInterfaceSameLine`, `FirstInterfaceSameLine`, `ImplementsLine`, `InterfaceSameLine`, `InterfaceWrongIndent`, `NewlineBeforeKeyword`, `NoSpaceBeforeName`, `OpenBraceNewLine`, `OpenBraceNotAlone`, `OpenBraceWrongLine`, `SpaceAfterKeyword`, `SpaceAfterName`, `SpaceBeforeBrace`, `SpaceBeforeComma`, `SpaceBeforeExtends`, `SpaceBeforeImplements`, `SpaceBeforeKeyword`, `SpaceBeforeName`: `mago format`<br>`CloseBraceSameLine`: Nothing<br>`ContentAfterBrace`: `mago format`, [`drupal/post-statement-comment`](../rules/comment-text.md#drupalpost-statement-comment)<br>`MissingBrace`: Mago `parse` | `CloseBraceSameLine`: A comment after the closing brace of a class, such as `} // end class`. The formatter keeps it and nothing reports it. phpcbf has no fix for it either. `MissingBrace`: A class without braces doesn't parse. Mago reports a `parse` error, and the formatter skips the file. |
| `Drupal.Classes.FullyQualifiedNamespace` | [`drupal/fully-qualified-name`](../rules/naming-and-imports.md#drupalfully-qualified-name) |  |
| `Drupal.Classes.PropertyDeclaration` | [`drupal/property-visibility`](../rules/statements.md#drupalproperty-visibility) |  |
| `Drupal.Classes.UseGlobalClass` | [`drupal/redundant-use`](../rules/naming-and-imports.md#drupalredundant-use) |  |

## Commenting

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Commenting.ClassComment` | `Missing`: [`drupal/missing-class-comment`](../rules/docblock-structure.md#drupalmissing-class-comment)<br>`Short`, `SpacingAfter`, `WrongStyle`: [`drupal/class-comment`](../rules/docblock-structure.md#drupalclass-comment) |  |
| `Drupal.Commenting.Deprecated` | [`drupal/deprecated-tag`](../rules/docblock-structure.md#drupaldeprecated-tag) |  |
| `Drupal.Commenting.DocComment` | `ContentAfterOpen`, `InheritDocWithoutBraces`, `ShortStartSpace`, `SpacingAfter`, `SpacingBeforeShort`, `SpacingBetween`, `TagValueIndent`, `WrongEnd`: [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment)<br>`Empty`: Mago `no-empty-comment`<br>`LongFullStop`: [`drupal/long-description-punctuation`](../rules/comment-text.md#drupallong-description-punctuation)<br>`LongNotCapital`, `ShortFullStop`, `ShortNotCapital`, `ShortSingleLine`: [`drupal/doc-sentence`](../rules/docblock-structure.md#drupaldoc-sentence)<br>`MissingShort`: [`drupal/missing-summary`](../rules/docblock-structure.md#drupalmissing-summary), partly<br>`ParamGroup`, `ParamNotFirst`, `SpacingAfterTagGroup`, `SpacingBeforeTags`, `TagGroupSpacing`, `TagsNotGrouped`: [`drupal/doc-tag-groups`](../rules/docblock-structure.md#drupaldoc-tag-groups) | `Empty`: Mago's rule, at note level. `MissingShort`: A `phpcs:` line above the summary is skipped, and the line below it counts as the summary. Coder reports it. With `--core`, only the `KernelTests`, `Unit` and `FunctionalJavascript` directories are checked, as in core's `phpcs.xml.dist`. |
| `Drupal.Commenting.DocCommentAlignment` | `NoSpaceAfterStar`, `SpaceAfterStar`: [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment)<br>`SpaceBeforeStar`: `mago format` |  |
| `Drupal.Commenting.DocCommentLongArraySyntax` | [`drupal/doc-comment-array-syntax`](../rules/comment-text.md#drupaldoc-comment-array-syntax) |  |
| `Drupal.Commenting.DocCommentStar` | `mago format` | The formatter puts the star back on a docblock line that has none. |
| `Drupal.Commenting.FileComment` | `FileTag`, `NamespaceNoFileDoc`: [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment)<br>`Missing`: [`drupal/missing-file-comment`](../rules/docblock-structure.md#drupalmissing-file-comment)<br>`SpacingAfterComment`: [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), `mago format`<br>`TemplateSpacingAfterComment`: Nothing<br>`WrongStyle`: [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), partly | `TemplateSpacingAfterComment`: A blank line between the file docblock and the `?>` that follows it, in a file of any extension. Not ported. |
| `Drupal.Commenting.FunctionComment` | `DuplicateReturn`, `MissingParamName`, `MissingParamType`: [`drupal/doc-tag-types`](../rules/docblock-structure.md#drupaldoc-tag-types)<br>`EmptySees`, `SeeAdditionalText`, `SeePunctuation`: [`drupal/doc-see-tag`](../rules/docblock-structure.md#drupaldoc-see-tag)<br>`ExtraParamComment`, `ParamNameNoCaseMatch`: `mago analyze` (`invalid-param-tag`)<br>`IncorrectParamVarName`, `InvalidReturn`: [`drupal/doc-type-name`](../rules/docblock-structure.md#drupaldoc-type-name)<br>`InvalidNoReturn`: `mago analyze` (`missing-return-statement`)<br>`InvalidReturnNotVoid`, `InvalidReturnVoid`: `mago analyze` (`invalid-return-statement`)<br>`InvalidThrows`: Mago `valid-docblock`<br>`Missing`: [`drupal/missing-function-comment`](../rules/docblock-structure.md#drupalmissing-function-comment)<br>`MissingParamComment`, `MissingReturnComment`, `ParamMissingDefinition`: [`drupal/doc-tag-descriptions`](../rules/docblock-structure.md#drupaldoc-tag-descriptions)<br>`MissingReturnType`: [`drupal/doc-tag-types`](../rules/docblock-structure.md#drupaldoc-tag-types), Mago `valid-docblock`<br>`ParamCommentFullStop`, `ParamCommentNotCapital`, `ThrowsNoFullStop`, `ThrowsNotCapital`: [`drupal/doc-sentence`](../rules/docblock-structure.md#drupaldoc-sentence)<br>`ParamCommentIndentation`, `ParamCommentNewLine`, `ParamNameDot`, `ParamTypeSpaces`, `ReturnCommentIndentation`, `ReturnTypeSpaces`, `ReturnVarName`, `SpacingAfterParamType`, `ThrowsComment`, `ThrowsCommentIndentation`: [`drupal/doc-tag-layout`](../rules/docblock-structure.md#drupaldoc-tag-layout)<br>`ParamNameNoMatch`: [`drupal/doc-tag-descriptions`](../rules/docblock-structure.md#drupaldoc-tag-descriptions), `mago analyze` (`invalid-param-tag`)<br>`SpacingAfter`, `WrongStyle`: [`drupal/function-comment`](../rules/docblock-structure.md#drupalfunction-comment) | `InvalidNoReturn`: Reported on the function, not on the `@return` tag. `InvalidReturnVoid`: Reported on the `return` statement, not on the `@return void` tag. `InvalidThrows`: A generic docblock parse error. |
| `Drupal.Commenting.GenderNeutralComment` | [`drupal/gender-neutral-comment`](../rules/comment-text.md#drupalgender-neutral-comment) |  |
| `Drupal.Commenting.HookComment` | [`drupal/hook-comment`](../rules/docblock-structure.md#drupalhook-comment) |  |
| `Drupal.Commenting.InlineComment` | `DocBlock`, `NoSpaceBefore`, `NotCapital`, `SpacingBefore`, `TabBefore`: [`drupal/inline-comment`](../rules/comment-text.md#drupalinline-comment)<br>`Empty`: Mago `no-empty-comment`<br>`InvalidEndChar`: [`drupal/inline-comment-punctuation`](../rules/comment-text.md#drupalinline-comment-punctuation)<br>`SpacingAfter`: [`drupal/inline-comment-blank-line`](../rules/comment-text.md#drupalinline-comment-blank-line), `mago format`, partly<br>`SpacingAfterAtFunctionEnd`: `mago format`<br>`WrongStyle`: `mago format`, [`drupal/inline-comment`](../rules/comment-text.md#drupalinline-comment) | `Empty`: Mago's rule, at note level. It also reports an empty `/* */`, which Coder doesn't. Its fix leaves a blank line where phpcbf removes the line. |
| `Drupal.Commenting.InlineVariableComment` | [`drupal/inline-variable-comment`](../rules/docblock-structure.md#drupalinline-variable-comment) |  |
| `Drupal.Commenting.PostStatementComment` | [`drupal/post-statement-comment`](../rules/comment-text.md#drupalpost-statement-comment) |  |
| `Drupal.Commenting.TodoComment` | [`drupal/todo-comment`](../rules/comment-text.md#drupaltodo-comment) |  |
| `Drupal.Commenting.VariableComment` | `DuplicateVar`, `EmptyVar`, `InlineVariableName`, `MissingVar`, `VarOrder`, `WrongStyle`: [`drupal/variable-comment`](../rules/docblock-structure.md#drupalvariable-comment)<br>`EmptySees`: [`drupal/doc-see-tag`](../rules/docblock-structure.md#drupaldoc-see-tag)<br>`IncorrectVarType`: [`drupal/doc-type-name`](../rules/docblock-structure.md#drupaldoc-type-name), partly<br>`Missing`: [`drupal/missing-property-comment`](../rules/docblock-structure.md#drupalmissing-property-comment) |  |

## ControlStructures

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.ControlStructures.ControlSignature` | `NewlineAfterCloseBrace`, `SpaceAfterCloseBrace`, `SpaceAfterCloseParenthesis`, `SpaceAfterKeyword`, `SpaceBeforeSemicolon`: `mago format`<br>`NewlineAfterOpenBrace`: `mago format`, partly | `NewlineAfterOpenBrace`: The formatter writes a `match` with one arm on one line, and Coder reports that line under this code and under `OpenBracketSpacing.OpeningWhitespace`, `CloseBracketSpacing.ClosingWhitespace` and `ScopeClosingBrace.Line`. |

## Files

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Files.EndFileNewline` | `mago format` | PHP files only. Coder also checks the last line of `.txt`, `.md` and `.yml` files. Mago doesn't read `.txt` and `.md` files, and the formatter skips `.yml` files. |
| `Drupal.Files.FileEncoding` | [`drupal/file-encoding`](../rules/files-and-tags.md#drupalfile-encoding) |  |
| `Drupal.Files.LineLength` | [`drupal/comment-line-length`](../rules/comment-text.md#drupalcomment-line-length) |  |
| `Drupal.Files.TxtFileLineLength` | phpcs | Checks `.txt` and `.md` files. Mago reads every file as PHP, and a `<?php` example in such a file gives parse errors, so they stay with phpcs. |

## Formatting

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Formatting.MultiLineAssignment` | `mago format` |  |
| `Drupal.Formatting.MultipleStatementAlignment` | `mago format` |  |
| `Drupal.Formatting.SpaceInlineIf` | `mago format` |  |
| `Drupal.Formatting.SpaceUnaryOperator` | `mago format` |  |

## Functions

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Functions.DiscouragedFunctions` | [`drupal/discouraged-function`](../rules/phpstan-drupal.md#drupaldiscouraged-function), Mago `no-eval` |  |
| `Drupal.Functions.MultiLineFunctionDeclaration` | `BraceOnNewLine`, `CloseBracketLine`, `CloseBracketNewLine`, `ContentAfterBrace`, `FirstParamSpacing`, `Indent`, `MissingTrailingComma`, `NewlineBeforeOpenBrace`, `OneParamPerLine`, `SpaceAfterFunction`, `SpaceAfterUse`, `SpaceBeforeBrace`, `SpaceBeforeOpenBrace`, `SpaceBeforeOpenParen`, `SpaceBeforeSemicolon`, `SpaceBeforeUse`, `UseCloseBracketLine`: `mago format`<br>`EmptyLine`: [`drupal/parameter-blank-line`](../rules/statements.md#drupalparameter-blank-line) |  |

## InfoFiles

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.InfoFiles.AutoAddedKeys` | [`drupal/info-auto-added-keys`](../rules/info-and-routing-files.md#drupalinfo-auto-added-keys) |  |
| `Drupal.InfoFiles.ClassFiles` | phpcs | Checks the `files[]` lines of a Drupal 7 `.info` file, which phpcs reads only when its extensions include `info`. It doesn't read `.info.yml` files. |
| `Drupal.InfoFiles.DependenciesArray` | [`drupal/info-dependencies-array`](../rules/info-and-routing-files.md#drupalinfo-dependencies-array) |  |
| `Drupal.InfoFiles.DuplicateEntry` | phpcs | Checks a Drupal 7 `.info` file, which phpcs reads only when its extensions include `info`. It doesn't read `.info.yml` files. |
| `Drupal.InfoFiles.Required` | phpcs | Checks a Drupal 7 `.info` file, which phpcs reads only when its extensions include `info`. It doesn't read `.info.yml` files. |

## NamingConventions

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.NamingConventions.ValidClassName` | `NoUnderscores`, `StartWithCapital`: Mago `class-name`, Mago `interface-name`, Mago `trait-name`, Mago `enum-name`<br>`NoUpperAcronyms`: [`drupal/class-name-acronym`](../rules/naming-and-imports.md#drupalclass-name-acronym) |  |
| `Drupal.NamingConventions.ValidEnumCase` | [`drupal/enum-case-name`](../rules/naming-and-imports.md#drupalenum-case-name) |  |
| `Drupal.NamingConventions.ValidFunctionName` | `InvalidName`: [`drupal/function-name`](../rules/naming-and-imports.md#drupalfunction-name)<br>`InvalidPrefix`: [`drupal/function-prefix`](../rules/procedural-files.md#drupalfunction-prefix)<br>`MethodDoubleUnderscore`: [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore)<br>`NotCamelCaps`, `ScopeNotCamelCaps`: Mago `method-name` (off by default) | Core doesn't run the sniff. Mago's `method-name` rule would cover the method codes, but it is off by default and the configuration in Setup doesn't turn it on. When on, it accepts a run of capitals, as in `getURL()`, which Coder reports. |
| `Drupal.NamingConventions.ValidGlobal` | [`drupal/global-variable`](../rules/procedural-files.md#drupalglobal-variable) |  |
| `Drupal.NamingConventions.ValidVariableName` | `LowerCamelName`: [`drupal/property-name`](../rules/naming-and-imports.md#drupalproperty-name)<br>`LowerStart`: Mago `variable-name` (off by default) | `LowerStart`: Mago's `variable-name` rule, off by default. The configuration in Setup doesn't turn it on. It reports a name that is neither camel case nor snake case, such as `$Foo`, and also `$foo_Bar`, which Coder accepts. It skips `foreach`, `catch`, `static` and `global` variables. |

## Scope

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Scope.MethodScope` | [`drupal/method-visibility`](../rules/statements.md#drupalmethod-visibility) |  |

## Semantics

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Drupal.Semantics.ConstantName` | `ConstConstantStart`: [`drupal/const-prefix`](../rules/procedural-files.md#drupalconst-prefix)<br>`ConstantStart`: [`drupal/constant-prefix`](../rules/procedural-files.md#drupalconstant-prefix) |  |
| `Drupal.Semantics.EmptyInstall` | [`drupal/empty-install-hook`](../rules/procedural-files.md#drupalempty-install-hook) |  |
| `Drupal.Semantics.FunctionAlias` | Mago `no-alias-function` | Mago's rule, on by default, with a fix that needs `--potentially-unsafe`. |
| `Drupal.Semantics.FunctionT` | `BackslashDoubleQuote`: [`drupal/translatable-string`](../rules/right-api.md#drupaltranslatable-string), `mago format`<br>`BackslashSingleQuote`, `Concat`, `EmptyString`, `EmptyT`, `WhiteSpace`: [`drupal/translatable-string`](../rules/right-api.md#drupaltranslatable-string)<br>`ConcatString`, `NotLiteralString`: [`drupal/translatable-literal`](../rules/right-api.md#drupaltranslatable-literal) |  |
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
| `Drupal.WhiteSpace.CloseBracketSpacing` | `mago format`, partly | The formatter writes `<?php }` for a closing brace after inline HTML, and Coder reports the space. Coder also reports the `match` with one arm that the formatter writes on one line, and the space it writes before `)` in a parameter or argument list that holds only a comment, as in `f( /* $x */ )`. |
| `Drupal.WhiteSpace.Comma` | `mago format` |  |
| `Drupal.WhiteSpace.EmptyLines` | `mago format` |  |
| `Drupal.WhiteSpace.ObjectOperatorIndent` | `mago format` |  |
| `Drupal.WhiteSpace.ObjectOperatorSpacing` | `mago format` |  |
| `Drupal.WhiteSpace.OpenBracketSpacing` | `mago format`, partly | The formatter keeps a space between an opening bracket and a comment that follows it, as in `foo( /* note */ $x)`, and Coder reports that space. Coder also reports the `match` with one arm that the formatter writes on one line, and the space it writes after `(` in a parameter or argument list that holds only a comment, as in `f( /* $x */ )`. |
| `Drupal.WhiteSpace.OpenTagNewline` | `mago format` |  |
| `Drupal.WhiteSpace.ScopeClosingBrace` | `BreakIndent`: `mago format`<br>`Indent`, `Line`: `mago format`, partly | `Indent`: A closing brace after inline HTML, on a `<?php }` line, keeps the indent of the HTML. The formatter doesn't change it, and Coder reports it. `Line`: The formatter writes a `match` with one arm on one line, and Coder reports its closing brace. |
| `Drupal.WhiteSpace.ScopeIndent` | `Incorrect`: `mago format`<br>`IncorrectExact`: `mago format`, partly | The formatter writes the indentation. `IncorrectExact`: The formatter gives a comment between a `break`, `return` or `throw` and the next `case` the indent of the case body. Coder wants the indent of the `case` label there, and reports it. The formatter also indents the body of an anonymous class with attributes one more level, and a `break` after a `return` in the same case, which the analyzer reports as `useless-control-flow`. |

<!-- /docs-gen -->
