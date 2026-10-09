# Sniffs from other standards

Coder's `Drupal` ruleset includes sniffs from PHP_CodeSniffer's own standards (Generic, PEAR,
PSR2, Squiz and Zend) and from the Slevomat Coding Standard. The VariableAnalysis sniff is part of
the `DrupalPractice` ruleset, on the [DrupalPractice standard](drupal-practice.md) page.

<!-- docs-gen:coder-other -->

## Generic

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Generic.Arrays.DisallowLongArraySyntax` | Mago `array-style` | Mago's rule, on by default, with a safe fix. |
| `Generic.CodeAnalysis.EmptyPHPStatement` | `EmptyPHPOpenCloseTagsDetected`: [`drupal/empty-php-tags`](../rules/files-and-tags.md#drupalempty-php-tags)<br>`SemicolonWithoutCodeDetected`: Mago `no-noop` |  |
| `Generic.CodeAnalysis.UselessOverridingMethod` | Mago `no-redundant-method-override`, partly | Mago's `no-redundant-method-override` reports an override only when the method has no parameters, because of a bug in Mago. |
| `Generic.ControlStructures.InlineControlStructure` | Mago `block-statement` | Mago's rule reports a body without braces, with no fix. The formatter keeps the body as it is. |
| `Generic.Files.ByteOrderMark` | [`drupal/byte-order-mark`](../rules/files-and-tags.md#drupalbyte-order-mark) |  |
| `Generic.Files.LineEndings` | `mago format` |  |
| `Generic.Formatting.DisallowMultipleStatements` | `mago format` |  |
| `Generic.Formatting.SpaceAfterCast` | `CommentFound`: [`drupal/comment-in-expression`](../rules/statements.md#drupalcomment-in-expression)<br>`NoSpace`, `TooLittleSpace`, `TooMuchSpace`: `mago format` |  |
| `Generic.Functions.FunctionCallArgumentSpacing` | `mago format` |  |
| `Generic.NamingConventions.ConstructorName` | Nothing | A PHP 4 constructor named after its class. Not ported yet. Since PHP 8 such a method is an ordinary method. `OldStyleCall`: A call to such a constructor through `parent::`. Not ported yet. |
| `Generic.NamingConventions.InterfaceNameSuffix` | Mago `interface-name` | With `interface-name = { psr = true }`. |
| `Generic.NamingConventions.TraitNameSuffix` | Mago `trait-name` | With `trait-name = { psr = true }`. Mago reports it at help level. |
| `Generic.NamingConventions.UpperCaseConstantName` | `ClassConstantNotUpperCase`: Mago `constant-name`<br>`ConstantNotUpperCase`: Mago `constant-name`, [`drupal/define-name`](../rules/naming-and-imports.md#drupaldefine-name) |  |
| `Generic.PHP.DeprecatedFunctions` | `mago analyze` (`deprecated-function`) |  |
| `Generic.PHP.DisallowShortOpenTag` | `EchoFound`: [`drupal/short-echo-tag`](../rules/files-and-tags.md#drupalshort-echo-tag)<br>`Found`, `PossibleFound`: Mago `no-short-opening-tag` |  |
| `Generic.PHP.LowerCaseKeyword` | `mago format`, Mago `lowercase-keyword` | The formatter lowercases keywords. `lowercase-keyword` fixes `AND`, `OR`, `XOR` and `INSTANCEOF`, which the formatter keeps. |
| `Generic.PHP.UpperCaseConstant` | `mago format`, partly | The drupal preset writes `TRUE`, `FALSE` and `NULL`. A fully qualified `\true` stays lower case and nothing reports it. |
| `Generic.Strings.UnnecessaryStringConcat` | Mago `no-redundant-string-concat` |  |
| `Generic.WhiteSpace.DisallowTabIndent` | `mago format` |  |
| `Generic.WhiteSpace.LanguageConstructSpacing` | `Incorrect`, `IncorrectSingle`, `IncorrectYieldFrom`: `mago format`, partly<br>`IncorrectYieldFromWithComment`: [`drupal/comment-in-expression`](../rules/statements.md#drupalcomment-in-expression) | `IncorrectSingle`: The formatter can break a long expression right after `print`, which Coder reports. |

## PEAR

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `PEAR.Files.IncludingFile` | `mago format` |  |
| `PEAR.Functions.FunctionCallSignature` | `mago format` |  |
| `PEAR.Functions.ValidDefaultValue` | Mago `optional-param-order` |  |

## PSR2

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `PSR2.Classes.PropertyDeclaration` | `AbstractAfterVisibility`, `AvizKeywordOrder`, `FinalAfterVisibility`, `ReadonlyBeforeVisibility`, `SpacingAfterType`, `StaticBeforeVisibility`: `mago format`<br>`Multiple`: [`drupal/property-per-statement`](../rules/statements.md#drupalproperty-per-statement)<br>`ScopeMissing`: [`drupal/property-visibility`](../rules/statements.md#drupalproperty-visibility)<br>`Underscore`: [`drupal/property-name`](../rules/naming-and-imports.md#drupalproperty-name) |  |
| `PSR2.ControlStructures.ElseIfDeclaration` | [`drupal/else-if`](../rules/statements.md#drupalelse-if) |  |
| `PSR2.ControlStructures.SwitchDeclaration` | `BreakNotNewLine`, `caseNotLower`, `defaultNotLower`, `SpaceBeforeColonCASE`, `SpaceBeforeColonDEFAULT`, `SpacingAfterCase`: `mago format`<br>`TerminatingComment`: [`drupal/case-fall-through`](../rules/statements.md#drupalcase-fall-through)<br>`WrongOpener`: Mago `no-redundant-block`<br>`WrongOpenercase`, `WrongOpenerdefault`: [`drupal/case-semicolon`](../rules/statements.md#drupalcase-semicolon) | `WrongOpener`: `case 1: {`. Mago reports the braces as a redundant block. |
| `PSR2.Methods.MethodDeclaration` | `AbstractAfterVisibility`, `FinalAfterVisibility`, `StaticBeforeVisibility`: `mago format`<br>`Underscore`: [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore) |  |
| `PSR2.Namespaces.NamespaceDeclaration` | `mago format` |  |
| `PSR2.Namespaces.UseDeclaration` | `MultipleDeclarations`, `SpaceAfterLastUse`, `SpaceAfterUse`: `mago format`<br>`UseBeforeNamespace`: Mago `semantics` | `UseBeforeNamespace`: PHP refuses a `use` before the namespace, and Mago reports it as a semantic error. |

## Squiz

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Squiz.Arrays.ArrayBracketSpacing` | `mago format` |  |
| `Squiz.Arrays.ArrayDeclaration` | `CommaAfterLast`, `NoSpaceAfterComma`, `NoSpaceAfterDoubleArrow`, `NoSpaceBeforeDoubleArrow`, `SpaceAfterComma`, `SpaceAfterDoubleArrow`, `SpaceAfterKeyword`, `SpaceBeforeComma`, `SpaceBeforeDoubleArrow`, `SpaceInEmptyArray`: `mago format`<br>`KeySpecified`, `NoKeySpecified`: Nothing | `KeySpecified`: An array that mixes keyed and unkeyed entries. Not ported yet. Core turns the code off. `NoKeySpecified`: An array that mixes keyed and unkeyed entries. Not ported yet. Core turns the code off. |
| `Squiz.Classes.ClassFileName` | Mago `file-name`, Mago `single-class-per-file`, partly | With `file-name = { enabled = true }`. With `file-name = { enabled = true }`. In a file with several classes, `single-class-per-file` reports the extra class instead. |
| `Squiz.ControlStructures.ForEachLoopDeclaration` | `mago format` |  |
| `Squiz.ControlStructures.ForLoopDeclaration` | `mago format` |  |
| `Squiz.ControlStructures.SwitchDeclaration` | `CaseNotLower`, `ContentAfterCase`, `ContentBeforeBreak`, `DefaultNotLower`, `SpaceBeforeColonCase`, `SpaceBeforeColonDefault`: `mago format`<br>`MissingCase`: [`drupal/empty-switch`](../rules/statements.md#drupalempty-switch)<br>`SpacingAfterBreak`: [`drupal/case-break-blank-line`](../rules/statements.md#drupalcase-break-blank-line) |  |
| `Squiz.Functions.FunctionDeclarationArgumentSpacing` | `mago format` |  |
| `Squiz.PHP.LowercasePHPFunctions` | `mago analyze` (`incorrect-function-casing`) | With `check-name-casing = true`. Reported without a fix. |
| `Squiz.PHP.NonExecutableCode` | `ReturnNotRequired`: [`drupal/redundant-return`](../rules/statements.md#drupalredundant-return)<br>`Unreachable`: `mago analyze` (`unevaluated-code`), `mago analyze` (`useless-control-flow`) | `Unreachable`: A `break;` after `return` is `useless-control-flow`. |
| `Squiz.Strings.ConcatenationSpacing` | `mago format` |  |
| `Squiz.WhiteSpace.FunctionSpacing` | `mago format`, partly | `AfterLast`: The formatter removes the blank lines around a function declared inside `if (!function_exists())`, which Coder reports. `BeforeFirst`: The formatter removes the blank lines around a function declared inside `if (!function_exists())`, which Coder reports. |
| `Squiz.WhiteSpace.OperatorSpacing` | `mago format`, partly | `NoSpaceAfter`: The formatter writes `catch (A\|B $e)`, which Coder reports. `NoSpaceBefore`: The formatter writes `catch (A\|B $e)`, which Coder reports. |
| `Squiz.WhiteSpace.ScopeKeywordSpacing` | `mago format` |  |
| `Squiz.WhiteSpace.SemicolonSpacing` | `mago format` |  |
| `Squiz.WhiteSpace.SuperfluousWhitespace` | `EmptyLines`, `EndFile`, `EndLine`: `mago format`<br>`StartFile`: [`drupal/file-start-whitespace`](../rules/files-and-tags.md#drupalfile-start-whitespace) |  |

## Zend

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Zend.Files.ClosingTag` | `mago format`, Mago `no-closing-tag` | The formatter removes a closing tag at the end of the file. One that ends a statement without a `;` stays. |

## Slevomat Coding Standard

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `SlevomatCodingStandard.Classes.BackedEnumTypeSpacing` | `mago format` |  |
| `SlevomatCodingStandard.Commenting.ForbiddenComments` | [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment) |  |
| `SlevomatCodingStandard.ControlStructures.NewWithParentheses` | `mago format` |  |
| `SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator` | [`drupal/null-coalesce`](../rules/statements.md#drupalnull-coalesce) |  |
| `SlevomatCodingStandard.Namespaces.UnusedUses` | Mago `no-redundant-use`, [`drupal/doc-type-namespace`](../rules/comment-text.md#drupaldoc-type-namespace), partly |  |
| `SlevomatCodingStandard.Namespaces.UseDoesNotStartWithBackslash` | [`drupal/use-leading-backslash`](../rules/naming-and-imports.md#drupaluse-leading-backslash) |  |
| `SlevomatCodingStandard.Namespaces.UseFromSameNamespace` | Mago `no-redundant-use` |  |
| `SlevomatCodingStandard.PHP.ShortList` | [`drupal/short-list`](../rules/statements.md#drupalshort-list) |  |
| `SlevomatCodingStandard.TypeHints.DeclareStrictTypes` | `IncorrectStrictTypesFormat`, `IncorrectWhitespaceAfterDeclare`: `mago format`<br>`IncorrectWhitespaceBeforeDeclare`: `mago format`, [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), partly |  |
| `SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue` | Mago `explicit-nullable-param` (off by default) | Mago's rule, with the same fix, runs only when `php-version` is 8.4 or later, where PHP deprecates the implicit nullable type. On an 8.3 target nothing reports it, even with the rule switched on. Mago's rule, with the same fix, runs only when `php-version` is 8.4 or later. With the README's 8.3, nothing reports it, even with the rule switched on. |

<!-- /docs-gen -->
