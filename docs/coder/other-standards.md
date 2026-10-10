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
| `Generic.CodeAnalysis.UselessOverridingMethod` | Mago `no-redundant-method-override`, partly | Mago's `no-redundant-method-override` compares a spread argument with a variadic parameter the wrong way round. It misses an override that passes its parameters through, and reports one that spreads an array parameter, such as `parent::foo(...$items)`. |
| `Generic.ControlStructures.InlineControlStructure` | Mago `block-statement` | Mago's rule reports a body without braces, with no fix. The formatter keeps the body as it is. The rule also reports a loop or `if` whose body is a lone `;`, such as `while (next($items));`, which Coder skips. |
| `Generic.Files.ByteOrderMark` | [`drupal/byte-order-mark`](../rules/files-and-tags.md#drupalbyte-order-mark) |  |
| `Generic.Files.LineEndings` | `mago format` |  |
| `Generic.Formatting.DisallowMultipleStatements` | `mago format` |  |
| `Generic.Formatting.SpaceAfterCast` | `CommentFound`: [`drupal/comment-in-expression`](../rules/statements.md#drupalcomment-in-expression)<br>`NoSpace`, `TooLittleSpace`, `TooMuchSpace`: `mago format` |  |
| `Generic.Functions.FunctionCallArgumentSpacing` | `mago format` |  |
| `Generic.NamingConventions.ConstructorName` | Nothing | A PHP 4 constructor named after its class. Not ported. Since PHP 8 such a method is an ordinary method. `OldStyleCall`: A call to such a constructor through `parent::`. Not ported. |
| `Generic.NamingConventions.InterfaceNameSuffix` | Mago `interface-name` | With `interface-name = { psr = true }`. Coder ignores case in the suffix, so it accepts `Foointerface`; Mago wants `Interface`. Mago also reports an interface name that is not in class case, which Coder reports under `Drupal.NamingConventions.ValidClassName`. |
| `Generic.NamingConventions.TraitNameSuffix` | Mago `trait-name` | With `trait-name = { psr = true }`. Mago reports it at help level. Coder ignores case in the suffix, so it accepts `Footrait`; Mago wants `Trait`. Mago also reports a trait name that is not in class case, which Coder reports under `Drupal.NamingConventions.ValidClassName`. |
| `Generic.NamingConventions.UpperCaseConstantName` | `ClassConstantNotUpperCase`: Mago `constant-name`<br>`ConstantNotUpperCase`: Mago `constant-name`, [`drupal/define-name`](../rules/naming-and-imports.md#drupaldefine-name) | `ClassConstantNotUpperCase`: Mago's `constant-name` also reports an upper-case name with a leading, trailing or doubled underscore, such as `A__B`, which Coder accepts. |
| `Generic.PHP.DeprecatedFunctions` | `mago analyze` (`deprecated-function`) | phpcs takes the deprecated functions from the running PHP, and the analyzer from Mago's stubs. Mago's stub for `strptime()` has no deprecation, so the analyzer does not report it. The analyzer also reports a function marked `@deprecated` in the codebase, and a call through a variable that holds a function name. |
| `Generic.PHP.DisallowShortOpenTag` | `EchoFound`: [`drupal/short-echo-tag`](../rules/files-and-tags.md#drupalshort-echo-tag)<br>`Found`, `PossibleFound`: Mago `no-short-opening-tag` | `Found`: Coder reports `<?` only when PHP's `short_open_tag` is on. Mago always reports it. Mago's fix writes no space after `<?php`, so `<?echo 1;` becomes `<?phpecho 1;`, which does not parse. `PossibleFound`: Mago always reads `<?` as an opening tag, so a `<?` in HTML text that is not PHP code gives parse errors. |
| `Generic.PHP.LowerCaseKeyword` | `mago format`, Mago `lowercase-keyword` | The formatter lowercases keywords. `lowercase-keyword` fixes `AND`, `OR`, `XOR` and `INSTANCEOF`, which the formatter keeps. |
| `Generic.PHP.UpperCaseConstant` | `mago format`, partly | The drupal preset writes `TRUE`, `FALSE` and `NULL`. A fully qualified `\true` stays lower case and nothing reports it. |
| `Generic.Strings.UnnecessaryStringConcat` | Mago `no-redundant-string-concat` |  |
| `Generic.WhiteSpace.DisallowTabIndent` | `mago format`, partly | `NonIndentTabsUsed`: The formatter keeps comment text as written, so a tab inside a comment stays. `TabsUsed`: The formatter does not change the indent of HTML outside the PHP tags. |
| `Generic.WhiteSpace.LanguageConstructSpacing` | `Incorrect`, `IncorrectYieldFrom`: `mago format`<br>`IncorrectSingle`: `mago format`, partly<br>`IncorrectYieldFromWithComment`: [`drupal/comment-in-expression`](../rules/statements.md#drupalcomment-in-expression) | `IncorrectSingle`: The formatter can break a long expression right after `echo`, `print`, `include`, `include_once`, `require` or `require_once`, which Coder reports. |

## PEAR

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `PEAR.Files.IncludingFile` | `mago format` |  |
| `PEAR.Functions.FunctionCallSignature` | `mago format` |  |
| `PEAR.Functions.ValidDefaultValue` | Mago `optional-param-order` | Mago also reports a typed parameter with a null default before a required one, such as `int $a = NULL, $b`. Coder skips it, because before PHP 8 that was how a type allowed NULL. PHP 8.1 deprecates it. |

## PSR2

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `PSR2.Classes.PropertyDeclaration` | `AbstractAfterVisibility`, `AvizKeywordOrder`, `FinalAfterVisibility`, `ReadonlyBeforeVisibility`, `SpacingAfterType`, `StaticBeforeVisibility`: `mago format`<br>`Multiple`: [`drupal/property-per-statement`](../rules/statements.md#drupalproperty-per-statement)<br>`ScopeMissing`: [`drupal/property-visibility`](../rules/statements.md#drupalproperty-visibility)<br>`Underscore`: [`drupal/property-name`](../rules/naming-and-imports.md#drupalproperty-name) |  |
| `PSR2.ControlStructures.ElseIfDeclaration` | [`drupal/else-if`](../rules/statements.md#drupalelse-if) |  |
| `PSR2.ControlStructures.SwitchDeclaration` | `BodyOnNextLineDEFAULT`, `BreakNotNewLine`, `caseNotLower`, `defaultNotLower`, `SpaceBeforeColonCASE`, `SpaceBeforeColonDEFAULT`, `SpacingAfterCase`: `mago format`<br>`TerminatingComment`: [`drupal/case-fall-through`](../rules/statements.md#drupalcase-fall-through)<br>`WrongOpener`: Mago `no-redundant-block`<br>`WrongOpenercase`, `WrongOpenerdefault`: [`drupal/case-semicolon`](../rules/statements.md#drupalcase-semicolon) | `WrongOpener`: `case 1: {`. Mago reports the braces as a redundant block. |
| `PSR2.Methods.MethodDeclaration` | `AbstractAfterVisibility`, `FinalAfterVisibility`, `StaticBeforeVisibility`: `mago format`<br>`Underscore`: [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore) |  |
| `PSR2.Namespaces.NamespaceDeclaration` | `mago format` |  |
| `PSR2.Namespaces.UseDeclaration` | `MultipleDeclarations`, `SpaceAfterLastUse`, `SpaceAfterUse`: `mago format`<br>`UseBeforeNamespace`: Mago `semantics` | `UseBeforeNamespace`: PHP refuses a `use` before the namespace, and Mago reports it as a semantic error. |

## Squiz

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Squiz.Arrays.ArrayBracketSpacing` | `mago format` |  |
| `Squiz.Arrays.ArrayDeclaration` | `CommaAfterLast`, `NoSpaceAfterComma`, `NoSpaceAfterDoubleArrow`, `NoSpaceBeforeDoubleArrow`, `SpaceAfterComma`, `SpaceAfterDoubleArrow`, `SpaceAfterKeyword`, `SpaceBeforeComma`, `SpaceBeforeDoubleArrow`, `SpaceInEmptyArray`: `mago format`<br>`KeySpecified`, `NoKeySpecified`: Nothing | `KeySpecified`: An array that mixes keyed and unkeyed entries. Not ported. Core turns the code off. `NoKeySpecified`: An array that mixes keyed and unkeyed entries. Not ported. Core turns the code off. |
| `Squiz.Classes.ClassFileName` | Mago `file-name`, Mago `single-class-per-file`, partly | With `file-name = { enabled = true }`. Coder's ruleset skips `*Test.php` and `*TestBase.php` files under `tests/<dir>/`. Mago's rule checks them too. `file-name` checks only a file with one class-like declaration. In a file with several, `single-class-per-file` reports every declaration after the first, whatever its name. |
| `Squiz.ControlStructures.ForEachLoopDeclaration` | `mago format` |  |
| `Squiz.ControlStructures.ForLoopDeclaration` | `NoSpaceAfterFirst`, `NoSpaceAfterSecond`, `SpacingBeforeFirst`: `mago format`<br>`SpacingAfterFirst`, `SpacingAfterSecond`: `mago format`, partly | `SpacingAfterFirst`: The formatter puts each part of a long `for` header on its own line, and Coder reports the line break. `SpacingAfterSecond`: The formatter puts each part of a long `for` header on its own line, and Coder reports the line break. |
| `Squiz.ControlStructures.SwitchDeclaration` | `CaseNotLower`, `ContentAfterCase`, `ContentAfterDefault`, `ContentBeforeBreak`, `DefaultNotLower`, `SpacingAfterDefault`: `mago format`<br>`ContentBeforeCase`, `ContentBeforeDefault`: `mago format`, partly<br>`MissingCase`: [`drupal/empty-switch`](../rules/statements.md#drupalempty-switch)<br>`SpaceBeforeColonCase`, `SpaceBeforeColonDefault`: `mago format`, Mago `no-redundant-block`<br>`SpacingAfterBreak`: [`drupal/case-break-blank-line`](../rules/statements.md#drupalcase-break-blank-line), partly<br>`WrongOpenerCase`, `WrongOpenerDefault`: Nothing | `ContentBeforeCase`: The formatter keeps a block comment before the label on the label's line, as in `/* note */ case 1:`, which Coder reports. `ContentBeforeDefault`: The formatter keeps a block comment before the label on the label's line, as in `/* note */ default:`, which Coder reports. `SpaceBeforeColonCase`: Coder also reports `case 1: {`. Mago reports the braces as a redundant block. `SpaceBeforeColonDefault`: Coder also reports `default: {`. Mago reports the braces as a redundant block. `WrongOpenerCase`: `case 1 ?>`, which PHP accepts. Mago cannot parse it and reports a parse error. `WrongOpenerDefault`: `default ?>`, which PHP accepts. Mago cannot parse it and reports a parse error. |
| `Squiz.Functions.FunctionDeclarationArgumentSpacing` | `mago format` |  |
| `Squiz.PHP.LowercasePHPFunctions` | `mago analyze` (`incorrect-function-casing`) | With `check-name-casing = true`. Reported without a fix. The analyzer skips a first-class callable such as `STRLEN(...)`. It also reports a call to a function of the codebase whose case differs from the declaration, where Coder checks only PHP's own functions. |
| `Squiz.PHP.NonExecutableCode` | `ReturnNotRequired`: [`drupal/redundant-return`](../rules/statements.md#drupalredundant-return)<br>`Unreachable`: `mago analyze` (`unevaluated-code`), `mago analyze` (`useless-control-flow`) | `Unreachable`: A `break;` after `return` is `useless-control-flow`. The analyzer also reports code after an `if` and `else` that both exit, and code after an exit on the same line, which Coder skips. |
| `Squiz.Strings.ConcatenationSpacing` | `mago format` |  |
| `Squiz.WhiteSpace.FunctionSpacing` | `After`: `mago format`<br>`AfterLast`, `Before`, `BeforeFirst`: `mago format`, partly | `AfterLast`: The formatter removes the blank line between a function and the end of a block, such as `if (!function_exists())` or a loop, which Coder reports. `Before`: The formatter adds no blank line between a statement and a function declared after it, such as a `define()` call followed by a function, or a statement followed by a nested function. `BeforeFirst`: The formatter removes the blank line between the start of a block, such as `if (!function_exists())` or a loop, and a function, which Coder reports. |
| `Squiz.WhiteSpace.OperatorSpacing` | `NoSpaceAfter`, `NoSpaceBefore`: `mago format`, partly<br>`NoSpaceAfterAmp`, `NoSpaceBeforeAmp`, `SpacingAfter`, `SpacingAfterAmp`, `SpacingBefore`, `SpacingBeforeAmp`: `mago format` | `NoSpaceAfter`: The formatter writes `catch (A\|B $e)`, which Coder reports. `NoSpaceBefore`: The formatter writes `catch (A\|B $e)`, which Coder reports. |
| `Squiz.WhiteSpace.ScopeKeywordSpacing` | `mago format` |  |
| `Squiz.WhiteSpace.SemicolonSpacing` | `mago format`, Mago `no-noop` | The formatter puts an empty statement after a `}`, as in `};`, on a line of its own, which Coder reports. `no-noop` removes that statement. |
| `Squiz.WhiteSpace.SuperfluousWhitespace` | `EmptyLines`, `EndFile`, `EndLine`: `mago format`<br>`StartFile`: [`drupal/file-start-whitespace`](../rules/files-and-tags.md#drupalfile-start-whitespace) |  |

## Zend

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `Zend.Files.ClosingTag` | `mago format`, Mago `no-closing-tag` | The formatter removes a closing tag at the end of the file. When the last statement has no `;`, the tag stays and nothing reports it. The formatter moves it onto that statement's line. Coder's ruleset skips `*.tpl.php` files. Mago checks them too. |

## Slevomat Coding Standard

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `SlevomatCodingStandard.Classes.BackedEnumTypeSpacing` | `mago format` |  |
| `SlevomatCodingStandard.Commenting.ForbiddenComments` | [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment) |  |
| `SlevomatCodingStandard.ControlStructures.NewWithParentheses` | `mago format` |  |
| `SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator` | [`drupal/null-coalesce`](../rules/statements.md#drupalnull-coalesce) |  |
| `SlevomatCodingStandard.Namespaces.UnusedUses` | Mago `no-redundant-use`, [`drupal/doc-type-namespace`](../rules/comment-text.md#drupaldoc-type-namespace), partly |  |
| `SlevomatCodingStandard.Namespaces.UseDoesNotStartWithBackslash` | [`drupal/use-leading-backslash`](../rules/naming-and-imports.md#drupaluse-leading-backslash) |  |
| `SlevomatCodingStandard.Namespaces.UseFromSameNamespace` | Mago `no-redundant-use` | Mago also reports each name in a group import and each name after the first in a comma list. Coder skips those. |
| `SlevomatCodingStandard.PHP.ShortList` | [`drupal/short-list`](../rules/statements.md#drupalshort-list) |  |
| `SlevomatCodingStandard.TypeHints.DeclareStrictTypes` | `IncorrectStrictTypesFormat`, `IncorrectWhitespaceAfterDeclare`: `mago format`<br>`IncorrectWhitespaceBeforeDeclare`: `mago format`, [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment), partly |  |
| `SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue` | Mago `explicit-nullable-param` (off by default) | Mago's rule, with the same fix, runs only when `php-version` is 8.4 or later, where PHP deprecates the implicit nullable type. On an 8.3 target nothing reports it, even with the rule switched on. |

<!-- /docs-gen -->
