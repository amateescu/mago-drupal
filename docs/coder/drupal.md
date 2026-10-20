# Drupal standard

The sniffs of Coder's own `Drupal` standard, by category. The sniffs taken from other standards are
on [Sniffs from other standards](other-standards.md).

<!-- docs-gen:coder-drupal -->

## Arrays

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| [`Drupal.Arrays.Array`](#drupalarraysarray) | `mago format`, partly |

### `Drupal.Arrays.Array`

The drupal preset writes the trailing comma, the indentation and the line breaks of a long array.

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `ArrayClosingIndentation`, `ArrayIndentation` | `mago format`, partly |
| `CommaLastItem`, `LongLineDeclaration` | `mago format` |

- `ArrayClosingIndentation`: An array that opens on a continuation line gets the indent of the statement's first line. Coder counts from the line the array opens on, and reports its items and closing bracket.
- `ArrayIndentation`: When the value after `=>` breaks over lines, such as a long condition, the formatter writes the next lines at the key's indent. Coder wants them one level deeper. The formatter also writes the broken `&&` or `||` condition of a ternary in an array element at the element's indent, and breaks an array at a block comment between its elements, and Coder reports both.

## Attributes

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| `Drupal.Attributes.ValidHookName` | [`drupal/hook-attribute-name`](../rules/naming-and-imports.md#drupalhook-attribute-name) |

## Classes

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| [`Drupal.Classes.ClassDeclaration`](#drupalclassesclassdeclaration) | `mago format`, [`drupal/post-statement-comment`](../rules/comment-text.md#drupalpost-statement-comment), Mago `parse`, partly |
| `Drupal.Classes.FullyQualifiedNamespace` | [`drupal/fully-qualified-name`](../rules/naming-and-imports.md#drupalfully-qualified-name) |
| `Drupal.Classes.PropertyDeclaration` | [`drupal/property-visibility`](../rules/statements.md#drupalproperty-visibility) |
| `Drupal.Classes.UseGlobalClass` | [`drupal/redundant-use`](../rules/naming-and-imports.md#drupalredundant-use) |

### `Drupal.Classes.ClassDeclaration`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `BraceOnNewLine`, `CloseBraceAfterBody`, `ExtendsIndentation`, `ExtendsInterfaceSameLine`, `ExtendsLine`, `FirstExtendsInterfaceSameLine`, `FirstInterfaceSameLine`, `ImplementsLine`, `InterfaceSameLine`, `InterfaceWrongIndent`, `NewlineBeforeKeyword`, `NoSpaceBeforeName`, `OpenBraceNewLine`, `OpenBraceNotAlone`, `OpenBraceWrongLine`, `SpaceAfterKeyword`, `SpaceAfterName`, `SpaceBeforeBrace`, `SpaceBeforeComma`, `SpaceBeforeExtends`, `SpaceBeforeImplements`, `SpaceBeforeKeyword`, `SpaceBeforeName` | `mago format` |
| `CloseBraceSameLine` | [`drupal/post-statement-comment`](../rules/comment-text.md#drupalpost-statement-comment), `mago format`, partly |
| `ContentAfterBrace` | `mago format`, [`drupal/post-statement-comment`](../rules/comment-text.md#drupalpost-statement-comment) |
| `MissingBrace` | Mago `parse` |

- `CloseBraceSameLine`: `drupal/post-statement-comment` reports a comment after the closing brace of a class, such as `} // end class`. The formatter moves code after the brace to a line of its own and removes trailing spaces. A `?>` after the brace isn't reported.
- `MissingBrace`: A class without braces doesn't parse. Mago reports a `parse` error, and the formatter skips the file.

## Commenting

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| [`Drupal.Commenting.ClassComment`](#drupalcommentingclasscomment) | [`drupal/missing-class-comment`](../rules/docblock-structure.md#drupalmissing-class-comment), [`drupal/class-comment`](../rules/docblock-structure.md#drupalclass-comment) |
| `Drupal.Commenting.Deprecated` | [`drupal/deprecated-tag`](../rules/docblock-structure.md#drupaldeprecated-tag) |
| [`Drupal.Commenting.DocComment`](#drupalcommentingdoccomment) | [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment), Mago `no-empty-comment`, [`drupal/long-description-punctuation`](../rules/comment-text.md#drupallong-description-punctuation), [`drupal/doc-sentence`](../rules/docblock-structure.md#drupaldoc-sentence), [`drupal/missing-summary`](../rules/docblock-structure.md#drupalmissing-summary), [`drupal/doc-tag-groups`](../rules/docblock-structure.md#drupaldoc-tag-groups), partly |
| [`Drupal.Commenting.DocCommentAlignment`](#drupalcommentingdoccommentalignment) | [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment), `mago format` |
| `Drupal.Commenting.DocCommentLongArraySyntax` | [`drupal/doc-comment-array-syntax`](../rules/comment-text.md#drupaldoc-comment-array-syntax) |
| [`Drupal.Commenting.DocCommentStar`](#drupalcommentingdoccommentstar) | `mago format` |
| [`Drupal.Commenting.FileComment`](#drupalcommentingfilecomment) | [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), [`drupal/missing-file-comment`](../rules/docblock-structure.md#drupalmissing-file-comment), `mago format`, Nothing, partly |
| [`Drupal.Commenting.FunctionComment`](#drupalcommentingfunctioncomment) | [`drupal/doc-tag-types`](../rules/docblock-structure.md#drupaldoc-tag-types), [`drupal/doc-see-tag`](../rules/docblock-structure.md#drupaldoc-see-tag), `mago analyze` (`invalid-param-tag`), [`drupal/doc-type-name`](../rules/docblock-structure.md#drupaldoc-type-name), `mago analyze` (`missing-return-statement`), `mago analyze` (`invalid-return-statement`), Mago `valid-docblock`, [`drupal/missing-function-comment`](../rules/docblock-structure.md#drupalmissing-function-comment), [`drupal/doc-tag-descriptions`](../rules/docblock-structure.md#drupaldoc-tag-descriptions), [`drupal/doc-sentence`](../rules/docblock-structure.md#drupaldoc-sentence), [`drupal/doc-tag-layout`](../rules/docblock-structure.md#drupaldoc-tag-layout), [`drupal/function-comment`](../rules/docblock-structure.md#drupalfunction-comment) |
| `Drupal.Commenting.GenderNeutralComment` | [`drupal/gender-neutral-comment`](../rules/comment-text.md#drupalgender-neutral-comment) |
| `Drupal.Commenting.HookComment` | [`drupal/hook-comment`](../rules/docblock-structure.md#drupalhook-comment) |
| [`Drupal.Commenting.InlineComment`](#drupalcommentinginlinecomment) | [`drupal/inline-comment`](../rules/comment-text.md#drupalinline-comment), Mago `no-empty-comment`, [`drupal/inline-comment-punctuation`](../rules/comment-text.md#drupalinline-comment-punctuation), [`drupal/inline-comment-blank-line`](../rules/comment-text.md#drupalinline-comment-blank-line), `mago format`, partly |
| `Drupal.Commenting.InlineVariableComment` | [`drupal/inline-variable-comment`](../rules/docblock-structure.md#drupalinline-variable-comment) |
| `Drupal.Commenting.PostStatementComment` | [`drupal/post-statement-comment`](../rules/comment-text.md#drupalpost-statement-comment) |
| `Drupal.Commenting.TodoComment` | [`drupal/todo-comment`](../rules/comment-text.md#drupaltodo-comment) |
| [`Drupal.Commenting.VariableComment`](#drupalcommentingvariablecomment) | [`drupal/variable-comment`](../rules/docblock-structure.md#drupalvariable-comment), [`drupal/doc-see-tag`](../rules/docblock-structure.md#drupaldoc-see-tag), [`drupal/doc-type-name`](../rules/docblock-structure.md#drupaldoc-type-name), [`drupal/missing-property-comment`](../rules/docblock-structure.md#drupalmissing-property-comment), partly |

### `Drupal.Commenting.ClassComment`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `Missing` | [`drupal/missing-class-comment`](../rules/docblock-structure.md#drupalmissing-class-comment) |
| `Short`, `SpacingAfter`, `WrongStyle` | [`drupal/class-comment`](../rules/docblock-structure.md#drupalclass-comment) |

### `Drupal.Commenting.DocComment`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `ContentAfterOpen`, `InheritDocWithoutBraces`, `ShortStartSpace`, `SpacingAfter`, `SpacingBeforeShort`, `SpacingBetween`, `TagValueIndent`, `WrongEnd` | [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment) |
| `Empty` | Mago `no-empty-comment` |
| `LongFullStop` | [`drupal/long-description-punctuation`](../rules/comment-text.md#drupallong-description-punctuation) |
| `LongNotCapital`, `ShortFullStop`, `ShortNotCapital`, `ShortSingleLine` | [`drupal/doc-sentence`](../rules/docblock-structure.md#drupaldoc-sentence) |
| `MissingShort` | [`drupal/missing-summary`](../rules/docblock-structure.md#drupalmissing-summary), partly |
| `ParamGroup`, `ParamNotFirst`, `SpacingAfterTagGroup`, `SpacingBeforeTags`, `TagGroupSpacing`, `TagsNotGrouped` | [`drupal/doc-tag-groups`](../rules/docblock-structure.md#drupaldoc-tag-groups) |

- `Empty`: Mago's rule, at note level.
- `MissingShort`: A `phpcs:` line above the summary is skipped, and the line below it counts as the summary. Coder reports it. In Drupal core, only the `KernelTests`, `Unit` and `FunctionalJavascript` directories are checked, as in core's `phpcs.xml.dist`.

### `Drupal.Commenting.DocCommentAlignment`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `NoSpaceAfterStar`, `SpaceAfterStar` | [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment) |
| `SpaceBeforeStar` | `mago format` |

### `Drupal.Commenting.DocCommentStar`

The formatter puts the star back on a docblock line that doesn't have one.

### `Drupal.Commenting.FileComment`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `FileTag`, `NamespaceNoFileDoc` | [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment) |
| `Missing` | [`drupal/missing-file-comment`](../rules/docblock-structure.md#drupalmissing-file-comment) |
| `SpacingAfterComment` | [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), `mago format` |
| `TemplateSpacingAfterComment` | Nothing |
| `WrongStyle` | [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), partly |

- `TemplateSpacingAfterComment`: A blank line between the file docblock and the `?>` that follows it, in a file of any extension. Not ported.

### `Drupal.Commenting.FunctionComment`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `DuplicateReturn`, `MissingParamName`, `MissingParamType` | [`drupal/doc-tag-types`](../rules/docblock-structure.md#drupaldoc-tag-types) |
| `EmptySees`, `SeeAdditionalText`, `SeePunctuation` | [`drupal/doc-see-tag`](../rules/docblock-structure.md#drupaldoc-see-tag) |
| `ExtraParamComment`, `ParamNameNoCaseMatch` | `mago analyze` (`invalid-param-tag`) |
| `IncorrectParamVarName`, `InvalidReturn` | [`drupal/doc-type-name`](../rules/docblock-structure.md#drupaldoc-type-name) |
| `InvalidNoReturn` | `mago analyze` (`missing-return-statement`) |
| `InvalidReturnNotVoid`, `InvalidReturnVoid` | `mago analyze` (`invalid-return-statement`) |
| `InvalidThrows` | Mago `valid-docblock` |
| `Missing` | [`drupal/missing-function-comment`](../rules/docblock-structure.md#drupalmissing-function-comment) |
| `MissingParamComment`, `MissingReturnComment`, `ParamMissingDefinition` | [`drupal/doc-tag-descriptions`](../rules/docblock-structure.md#drupaldoc-tag-descriptions) |
| `MissingReturnType` | [`drupal/doc-tag-types`](../rules/docblock-structure.md#drupaldoc-tag-types), Mago `valid-docblock` |
| `ParamCommentFullStop`, `ParamCommentNotCapital`, `ThrowsNoFullStop`, `ThrowsNotCapital` | [`drupal/doc-sentence`](../rules/docblock-structure.md#drupaldoc-sentence) |
| `ParamCommentIndentation`, `ParamCommentNewLine`, `ParamNameDot`, `ParamTypeSpaces`, `ReturnCommentIndentation`, `ReturnTypeSpaces`, `ReturnVarName`, `SpacingAfterParamType`, `ThrowsComment`, `ThrowsCommentIndentation` | [`drupal/doc-tag-layout`](../rules/docblock-structure.md#drupaldoc-tag-layout) |
| `ParamNameNoMatch` | [`drupal/doc-tag-descriptions`](../rules/docblock-structure.md#drupaldoc-tag-descriptions), `mago analyze` (`invalid-param-tag`) |
| `SpacingAfter`, `WrongStyle` | [`drupal/function-comment`](../rules/docblock-structure.md#drupalfunction-comment) |

- `InvalidNoReturn`: Reported on the function, not on the `@return` tag.
- `InvalidReturnVoid`: Reported on the `return` statement, not on the `@return void` tag.
- `InvalidThrows`: A generic docblock parse error.

### `Drupal.Commenting.InlineComment`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `DocBlock`, `NoSpaceBefore`, `NotCapital`, `SpacingBefore`, `TabBefore` | [`drupal/inline-comment`](../rules/comment-text.md#drupalinline-comment) |
| `Empty` | Mago `no-empty-comment` |
| `InvalidEndChar` | [`drupal/inline-comment-punctuation`](../rules/comment-text.md#drupalinline-comment-punctuation) |
| `SpacingAfter` | [`drupal/inline-comment-blank-line`](../rules/comment-text.md#drupalinline-comment-blank-line), `mago format`, partly |
| `SpacingAfterAtFunctionEnd` | `mago format` |
| `WrongStyle` | `mago format`, [`drupal/inline-comment`](../rules/comment-text.md#drupalinline-comment) |

- `Empty`: Mago's rule, at note level. It also reports an empty `/* */`, which Coder doesn't. Its fix leaves a blank line where phpcbf removes the line.

### `Drupal.Commenting.VariableComment`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `DuplicateVar`, `EmptyVar`, `InlineVariableName`, `MissingVar`, `VarOrder`, `WrongStyle` | [`drupal/variable-comment`](../rules/docblock-structure.md#drupalvariable-comment) |
| `EmptySees` | [`drupal/doc-see-tag`](../rules/docblock-structure.md#drupaldoc-see-tag) |
| `IncorrectVarType` | [`drupal/doc-type-name`](../rules/docblock-structure.md#drupaldoc-type-name), partly |
| `Missing` | [`drupal/missing-property-comment`](../rules/docblock-structure.md#drupalmissing-property-comment) |

## ControlStructures

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| [`Drupal.ControlStructures.ControlSignature`](#drupalcontrolstructurescontrolsignature) | `mago format`, partly |

### `Drupal.ControlStructures.ControlSignature`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `NewlineAfterCloseBrace`, `SpaceAfterCloseBrace`, `SpaceAfterCloseParenthesis`, `SpaceAfterKeyword`, `SpaceBeforeSemicolon` | `mago format` |
| `NewlineAfterOpenBrace` | `mago format`, partly |

- `NewlineAfterOpenBrace`: The formatter writes a `match` with one arm on one line, and Coder reports that line under this code and under `OpenBracketSpacing.OpeningWhitespace`, `CloseBracketSpacing.ClosingWhitespace` and `ScopeClosingBrace.Line`.

## Files

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| [`Drupal.Files.EndFileNewline`](#drupalfilesendfilenewline) | `mago format` |
| `Drupal.Files.FileEncoding` | [`drupal/file-encoding`](../rules/files-and-tags.md#drupalfile-encoding) |
| `Drupal.Files.LineLength` | [`drupal/comment-line-length`](../rules/comment-text.md#drupalcomment-line-length) |
| [`Drupal.Files.TxtFileLineLength`](#drupalfilestxtfilelinelength) | phpcs |

### `Drupal.Files.EndFileNewline`

PHP files only. Coder also checks the last line of `.txt`, `.md` and `.yml` files. Mago doesn't read `.txt` and `.md` files, and the formatter skips `.yml` files.

### `Drupal.Files.TxtFileLineLength`

Checks `.txt` and `.md` files. Mago reads every file as PHP, and a `<?php` example in such a file gives parse errors, so they stay with phpcs.

## Formatting

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| `Drupal.Formatting.MultiLineAssignment` | `mago format` |
| `Drupal.Formatting.MultipleStatementAlignment` | `mago format` |
| `Drupal.Formatting.SpaceInlineIf` | `mago format` |
| `Drupal.Formatting.SpaceUnaryOperator` | `mago format` |

## Functions

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| `Drupal.Functions.DiscouragedFunctions` | [`drupal/discouraged-function`](../rules/phpstan-drupal.md#drupaldiscouraged-function), Mago `no-eval` |
| [`Drupal.Functions.MultiLineFunctionDeclaration`](#drupalfunctionsmultilinefunctiondeclaration) | `mago format`, [`drupal/parameter-blank-line`](../rules/statements.md#drupalparameter-blank-line) |

### `Drupal.Functions.MultiLineFunctionDeclaration`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `BraceOnNewLine`, `CloseBracketLine`, `CloseBracketNewLine`, `ContentAfterBrace`, `FirstParamSpacing`, `Indent`, `MissingTrailingComma`, `NewlineBeforeOpenBrace`, `OneParamPerLine`, `SpaceAfterFunction`, `SpaceAfterUse`, `SpaceBeforeBrace`, `SpaceBeforeOpenBrace`, `SpaceBeforeOpenParen`, `SpaceBeforeSemicolon`, `SpaceBeforeUse`, `UseCloseBracketLine` | `mago format` |
| `EmptyLine` | [`drupal/parameter-blank-line`](../rules/statements.md#drupalparameter-blank-line) |

## InfoFiles

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| `Drupal.InfoFiles.AutoAddedKeys` | [`drupal/info-auto-added-keys`](../rules/info-and-routing-files.md#drupalinfo-auto-added-keys) |
| [`Drupal.InfoFiles.ClassFiles`](#drupalinfofilesclassfiles) | phpcs |
| `Drupal.InfoFiles.DependenciesArray` | [`drupal/info-dependencies-array`](../rules/info-and-routing-files.md#drupalinfo-dependencies-array) |
| [`Drupal.InfoFiles.DuplicateEntry`](#drupalinfofilesduplicateentry) | phpcs |
| [`Drupal.InfoFiles.Required`](#drupalinfofilesrequired) | phpcs |

### `Drupal.InfoFiles.ClassFiles`

Checks the `files[]` lines of a Drupal 7 `.info` file, which phpcs reads only when its extensions include `info`. It doesn't read `.info.yml` files.

### `Drupal.InfoFiles.DuplicateEntry`

Checks a Drupal 7 `.info` file, which phpcs reads only when its extensions include `info`. It doesn't read `.info.yml` files.

### `Drupal.InfoFiles.Required`

Checks a Drupal 7 `.info` file, which phpcs reads only when its extensions include `info`. It doesn't read `.info.yml` files.

## NamingConventions

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| [`Drupal.NamingConventions.ValidClassName`](#drupalnamingconventionsvalidclassname) | Mago `class-name`, Mago `interface-name`, Mago `trait-name`, Mago `enum-name`, [`drupal/class-name-acronym`](../rules/naming-and-imports.md#drupalclass-name-acronym) |
| `Drupal.NamingConventions.ValidEnumCase` | [`drupal/enum-case-name`](../rules/naming-and-imports.md#drupalenum-case-name) |
| [`Drupal.NamingConventions.ValidFunctionName`](#drupalnamingconventionsvalidfunctionname) | [`drupal/function-name`](../rules/naming-and-imports.md#drupalfunction-name), [`drupal/function-prefix`](../rules/procedural-files.md#drupalfunction-prefix), [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore), [`drupal/method-name`](../rules/naming-and-imports.md#drupalmethod-name) |
| `Drupal.NamingConventions.ValidGlobal` | [`drupal/global-variable`](../rules/procedural-files.md#drupalglobal-variable) |
| [`Drupal.NamingConventions.ValidVariableName`](#drupalnamingconventionsvalidvariablename) | [`drupal/property-name`](../rules/naming-and-imports.md#drupalproperty-name), [`drupal/variable-name`](../rules/naming-and-imports.md#drupalvariable-name) |

### `Drupal.NamingConventions.ValidClassName`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `NoUnderscores`, `StartWithCapital` | Mago `class-name`, Mago `interface-name`, Mago `trait-name`, Mago `enum-name` |
| `NoUpperAcronyms` | [`drupal/class-name-acronym`](../rules/naming-and-imports.md#drupalclass-name-acronym) |

### `Drupal.NamingConventions.ValidFunctionName`

Core doesn't run the sniff.

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `InvalidName` | [`drupal/function-name`](../rules/naming-and-imports.md#drupalfunction-name) |
| `InvalidPrefix` | [`drupal/function-prefix`](../rules/procedural-files.md#drupalfunction-prefix) |
| `MethodDoubleUnderscore` | [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore) |
| `NotCamelCaps`, `ScopeNotCamelCaps` | [`drupal/method-name`](../rules/naming-and-imports.md#drupalmethod-name) |

### `Drupal.NamingConventions.ValidVariableName`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `LowerCamelName` | [`drupal/property-name`](../rules/naming-and-imports.md#drupalproperty-name) |
| `LowerStart` | [`drupal/variable-name`](../rules/naming-and-imports.md#drupalvariable-name) |

## Scope

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| `Drupal.Scope.MethodScope` | [`drupal/method-visibility`](../rules/statements.md#drupalmethod-visibility) |

## Semantics

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| [`Drupal.Semantics.ConstantName`](#drupalsemanticsconstantname) | [`drupal/const-prefix`](../rules/procedural-files.md#drupalconst-prefix), [`drupal/constant-prefix`](../rules/procedural-files.md#drupalconstant-prefix) |
| `Drupal.Semantics.EmptyInstall` | [`drupal/empty-install-hook`](../rules/procedural-files.md#drupalempty-install-hook) |
| [`Drupal.Semantics.FunctionAlias`](#drupalsemanticsfunctionalias) | Mago `no-alias-function` |
| [`Drupal.Semantics.FunctionT`](#drupalsemanticsfunctiont) | [`drupal/translatable-string`](../rules/right-api.md#drupaltranslatable-string), `mago format`, [`drupal/translatable-literal`](../rules/right-api.md#drupaltranslatable-literal) |
| `Drupal.Semantics.FunctionTriggerError` | [`drupal/deprecation-message`](../rules/right-api.md#drupaldeprecation-message) |
| `Drupal.Semantics.FunctionWatchdog` | [`drupal/watchdog-message`](../rules/drupal-7.md#drupalwatchdog-message) |
| `Drupal.Semantics.InstallHooks` | [`drupal/install-hook-location`](../rules/procedural-files.md#drupalinstall-hook-location) |
| `Drupal.Semantics.LStringTranslatable` | [`drupal/link-text-translatable`](../rules/drupal-7.md#drupallink-text-translatable) |
| `Drupal.Semantics.PregSecurity` | [`drupal/preg-security`](../rules/bugs-and-security.md#drupalpreg-security) |
| `Drupal.Semantics.RemoteAddress` | [`drupal/remote-address`](../rules/bugs-and-security.md#drupalremote-address) |
| `Drupal.Semantics.TInHookMenu` | [`drupal/t-in-hook-menu`](../rules/drupal-7.md#drupalt-in-hook-menu) |
| `Drupal.Semantics.TInHookSchema` | [`drupal/t-in-hook-schema`](../rules/procedural-files.md#drupalt-in-hook-schema) |
| `Drupal.Semantics.UnsilencedDeprecation` | [`drupal/unsilenced-deprecation`](../rules/right-api.md#drupalunsilenced-deprecation) |

### `Drupal.Semantics.ConstantName`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `ConstConstantStart` | [`drupal/const-prefix`](../rules/procedural-files.md#drupalconst-prefix) |
| `ConstantStart` | [`drupal/constant-prefix`](../rules/procedural-files.md#drupalconstant-prefix) |

### `Drupal.Semantics.FunctionAlias`

Mago's rule, on by default, with a fix that needs `--potentially-unsafe`.

### `Drupal.Semantics.FunctionT`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `BackslashDoubleQuote` | [`drupal/translatable-string`](../rules/right-api.md#drupaltranslatable-string), `mago format` |
| `BackslashSingleQuote`, `Concat`, `EmptyString`, `EmptyT`, `WhiteSpace` | [`drupal/translatable-string`](../rules/right-api.md#drupaltranslatable-string) |
| `ConcatString`, `NotLiteralString` | [`drupal/translatable-literal`](../rules/right-api.md#drupaltranslatable-literal) |

## WhiteSpace

| Sniff | Handled by<span class="coder-table"></span> |
| --- | --- |
| [`Drupal.WhiteSpace.CloseBracketSpacing`](#drupalwhitespaceclosebracketspacing) | `mago format`, partly |
| `Drupal.WhiteSpace.Comma` | `mago format` |
| `Drupal.WhiteSpace.EmptyLines` | `mago format` |
| `Drupal.WhiteSpace.ObjectOperatorIndent` | `mago format` |
| `Drupal.WhiteSpace.ObjectOperatorSpacing` | `mago format` |
| [`Drupal.WhiteSpace.OpenBracketSpacing`](#drupalwhitespaceopenbracketspacing) | `mago format`, partly |
| `Drupal.WhiteSpace.OpenTagNewline` | `mago format` |
| [`Drupal.WhiteSpace.ScopeClosingBrace`](#drupalwhitespacescopeclosingbrace) | `mago format`, partly |
| [`Drupal.WhiteSpace.ScopeIndent`](#drupalwhitespacescopeindent) | `mago format`, partly |

### `Drupal.WhiteSpace.CloseBracketSpacing`

The formatter writes `<?php }` for a closing brace after inline HTML, and Coder reports the space. Coder also reports the `match` with one arm that the formatter writes on one line, and the space it writes before `)` in a parameter or argument list that holds only a comment, as in `f( /* $x */ )`.

### `Drupal.WhiteSpace.OpenBracketSpacing`

The formatter keeps a space between an opening bracket and a comment that follows it, as in `foo( /* note */ $x)`, and Coder reports that space. Coder also reports the `match` with one arm that the formatter writes on one line, and the space it writes after `(` in a parameter or argument list that holds only a comment, as in `f( /* $x */ )`.

### `Drupal.WhiteSpace.ScopeClosingBrace`

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `BreakIndent` | `mago format` |
| `Indent`, `Line` | `mago format`, partly |

- `Indent`: A closing brace after inline HTML, on a `<?php }` line, keeps the indent of the HTML. The formatter doesn't change it, and Coder reports it.
- `Line`: The formatter writes a `match` with one arm on one line, and Coder reports its closing brace.

### `Drupal.WhiteSpace.ScopeIndent`

The formatter writes the indentation.

| Codes | Handled by<span class="coder-table"></span> |
| --- | --- |
| `Incorrect` | `mago format` |
| `IncorrectExact` | `mago format`, partly |

- `IncorrectExact`: The formatter gives a comment between a `break`, `return` or `throw` and the next `case` the indent of the case body. Coder wants the indent of the `case` label there, and reports it. The formatter also indents the body of an anonymous class with attributes one more level, and a `break` after a `return` in the same case, which the analyzer reports as `useless-control-flow`.

<!-- /docs-gen -->
