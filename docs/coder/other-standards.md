# Sniffs from other standards

Coder's `Drupal` ruleset includes sniffs from PHP_CodeSniffer's own standards (Generic, PEAR,
PSR2, Squiz and Zend) and from the Slevomat Coding Standard. The VariableAnalysis sniff is part of
the `DrupalPractice` ruleset, on the [DrupalPractice standard](drupal-practice.md) page.

<!-- docs-gen:coder-other -->

## Generic

| Sniff | Handled by |
| --- | --- |
| [`Generic.Arrays.DisallowLongArraySyntax`](#genericarraysdisallowlongarraysyntax) | Mago `array-style` |
| [`Generic.CodeAnalysis.EmptyPHPStatement`](#genericcodeanalysisemptyphpstatement) | [`drupal/empty-php-tags`](../rules/files-and-tags.md#drupalempty-php-tags), Mago `no-noop` |
| [`Generic.CodeAnalysis.UselessOverridingMethod`](#genericcodeanalysisuselessoverridingmethod) | Mago `no-redundant-method-override`, partly |
| [`Generic.ControlStructures.InlineControlStructure`](#genericcontrolstructuresinlinecontrolstructure) | Mago `block-statement` |
| `Generic.Files.ByteOrderMark` | [`drupal/byte-order-mark`](../rules/files-and-tags.md#drupalbyte-order-mark) |
| `Generic.Files.LineEndings` | `mago format` |
| `Generic.Formatting.DisallowMultipleStatements` | `mago format` |
| [`Generic.Formatting.SpaceAfterCast`](#genericformattingspaceaftercast) | [`drupal/comment-in-expression`](../rules/statements.md#drupalcomment-in-expression), `mago format` |
| `Generic.Functions.FunctionCallArgumentSpacing` | `mago format` |
| [`Generic.NamingConventions.ConstructorName`](#genericnamingconventionsconstructorname) | [`drupal/old-style-constructor`](../rules/naming-and-imports.md#drupalold-style-constructor), `mago analyze` (`non-existent-method`), partly |
| [`Generic.NamingConventions.InterfaceNameSuffix`](#genericnamingconventionsinterfacenamesuffix) | Mago `interface-name` |
| [`Generic.NamingConventions.TraitNameSuffix`](#genericnamingconventionstraitnamesuffix) | Mago `trait-name` |
| [`Generic.NamingConventions.UpperCaseConstantName`](#genericnamingconventionsuppercaseconstantname) | Mago `constant-name`, [`drupal/define-name`](../rules/naming-and-imports.md#drupaldefine-name) |
| [`Generic.PHP.DeprecatedFunctions`](#genericphpdeprecatedfunctions) | `mago analyze` (`deprecated-function`) |
| [`Generic.PHP.DisallowShortOpenTag`](#genericphpdisallowshortopentag) | [`drupal/short-echo-tag`](../rules/files-and-tags.md#drupalshort-echo-tag), Mago `no-short-opening-tag` |
| [`Generic.PHP.LowerCaseKeyword`](#genericphplowercasekeyword) | `mago format`, Mago `lowercase-keyword` |
| `Generic.PHP.UpperCaseConstant` | `mago format`, [`drupal/literal-constant-case`](../rules/naming-and-imports.md#drupalliteral-constant-case) |
| `Generic.Strings.UnnecessaryStringConcat` | Mago `no-redundant-string-concat` |
| `Generic.WhiteSpace.DisallowTabIndent` | `mago format`, [`drupal/tab-character`](../rules/files-and-tags.md#drupaltab-character) |
| [`Generic.WhiteSpace.LanguageConstructSpacing`](#genericwhitespacelanguageconstructspacing) | `mago format`, [`drupal/comment-in-expression`](../rules/statements.md#drupalcomment-in-expression), partly |

### `Generic.Arrays.DisallowLongArraySyntax`

Mago's rule, on by default, with a safe fix.

### `Generic.CodeAnalysis.EmptyPHPStatement`

| Codes | Handled by |
| --- | --- |
| `EmptyPHPOpenCloseTagsDetected` | [`drupal/empty-php-tags`](../rules/files-and-tags.md#drupalempty-php-tags) |
| `SemicolonWithoutCodeDetected` | Mago `no-noop` |

### `Generic.CodeAnalysis.UselessOverridingMethod`

Mago's `no-redundant-method-override` compares a spread argument with a variadic parameter the wrong way round. It misses an override that passes its parameters through, and reports one that spreads an array parameter, such as `parent::foo(...$items)`.

### `Generic.ControlStructures.InlineControlStructure`

Mago's rule reports a body without braces, with no fix. The formatter doesn't add braces. It moves the body to a line of its own. The rule also reports a loop or `if` whose body is a lone `;`, such as `while (next($items));`, which Coder skips.

### `Generic.Formatting.SpaceAfterCast`

| Codes | Handled by |
| --- | --- |
| `CommentFound` | [`drupal/comment-in-expression`](../rules/statements.md#drupalcomment-in-expression) |
| `NoSpace`, `TooLittleSpace`, `TooMuchSpace` | `mago format` |

### `Generic.NamingConventions.ConstructorName`

| Codes | Handled by |
| --- | --- |
| `OldStyle` | [`drupal/old-style-constructor`](../rules/naming-and-imports.md#drupalold-style-constructor) |
| `OldStyleCall` | `mago analyze` (`non-existent-method`), partly |

- `OldStyleCall`: A call such as `parent::Base()` in a constructor. `mago analyze` reports the call that fails on PHP 8, when the parent doesn't have a method of that name. A call to a parent method of that name that exists works, and `drupal/old-style-constructor` reports that method where it's declared. A parent class outside the scanned paths isn't reported.

### `Generic.NamingConventions.InterfaceNameSuffix`

With `interface-name = { psr = true }`. Mago reports it at help level. Coder ignores case in the suffix, so it accepts `Foointerface`; Mago wants `Interface`. Mago also reports an interface name that isn't in class case, which Coder reports under `Drupal.NamingConventions.ValidClassName`.

### `Generic.NamingConventions.TraitNameSuffix`

With `trait-name = { psr = true }`. Mago reports it at help level. Coder ignores case in the suffix, so it accepts `Footrait`; Mago wants `Trait`. Mago also reports a trait name that isn't in class case, which Coder reports under `Drupal.NamingConventions.ValidClassName`.

### `Generic.NamingConventions.UpperCaseConstantName`

| Codes | Handled by |
| --- | --- |
| `ClassConstantNotUpperCase` | Mago `constant-name` |
| `ConstantNotUpperCase` | Mago `constant-name`, [`drupal/define-name`](../rules/naming-and-imports.md#drupaldefine-name) |

- `ClassConstantNotUpperCase`: Mago's `constant-name` also reports an upper-case name with a leading, trailing or doubled underscore, such as `A__B`, which Coder accepts.

### `Generic.PHP.DeprecatedFunctions`

phpcs takes the deprecated functions from the running PHP, and the analyzer from Mago's stubs. Mago's stub for `strptime()` doesn't have a deprecation, so the analyzer doesn't report it. The analyzer also reports a function marked `@deprecated` in the codebase, and a call through a variable that holds a function name.

### `Generic.PHP.DisallowShortOpenTag`

| Codes | Handled by |
| --- | --- |
| `EchoFound` | [`drupal/short-echo-tag`](../rules/files-and-tags.md#drupalshort-echo-tag) |
| `Found`, `PossibleFound` | Mago `no-short-opening-tag` |

- `Found`: Coder reports `<?` only when PHP's `short_open_tag` is on. Mago always reports it. Mago's fix doesn't write a space after `<?php`, so `<?echo 1;` becomes `<?phpecho 1;`, which doesn't parse.
- `PossibleFound`: Mago always reads `<?` as an opening tag, so a `<?` in HTML text that isn't PHP code gives parse errors.

### `Generic.PHP.LowerCaseKeyword`

The formatter lowercases keywords. `lowercase-keyword` fixes `AND`, `OR`, `XOR` and `INSTANCEOF`, which the formatter keeps.

### `Generic.WhiteSpace.LanguageConstructSpacing`

| Codes | Handled by |
| --- | --- |
| `Incorrect`, `IncorrectYieldFrom` | `mago format` |
| `IncorrectSingle` | `mago format`, partly |
| `IncorrectYieldFromWithComment` | [`drupal/comment-in-expression`](../rules/statements.md#drupalcomment-in-expression) |

- `IncorrectSingle`: The formatter can break a long expression right after `echo`, `print`, `include`, `include_once`, `require` or `require_once`, which Coder reports. It also puts `new` alone on its line before an anonymous class with attributes.

## PEAR

| Sniff | Handled by |
| --- | --- |
| `PEAR.Files.IncludingFile` | `mago format` |
| `PEAR.Functions.FunctionCallSignature` | `mago format` |
| [`PEAR.Functions.ValidDefaultValue`](#pearfunctionsvaliddefaultvalue) | Mago `optional-param-order` |

### `PEAR.Functions.ValidDefaultValue`

Mago also reports a typed parameter with a null default before a required one, such as `int $a = NULL, $b`. Coder skips it, because before PHP 8 that was how a type allowed NULL. PHP 8.4 deprecates it. Coder also skips `?int $a = NULL, $b`, which PHP 8.1 deprecates.

## PSR2

| Sniff | Handled by |
| --- | --- |
| [`PSR2.Classes.PropertyDeclaration`](#psr2classespropertydeclaration) | `mago format`, [`drupal/property-per-statement`](../rules/statements.md#drupalproperty-per-statement), [`drupal/property-visibility`](../rules/statements.md#drupalproperty-visibility), [`drupal/property-name`](../rules/naming-and-imports.md#drupalproperty-name) |
| `PSR2.ControlStructures.ElseIfDeclaration` | [`drupal/else-if`](../rules/statements.md#drupalelse-if) |
| [`PSR2.ControlStructures.SwitchDeclaration`](#psr2controlstructuresswitchdeclaration) | `mago format`, [`drupal/case-fall-through`](../rules/statements.md#drupalcase-fall-through), Mago `no-redundant-block`, [`drupal/case-semicolon`](../rules/statements.md#drupalcase-semicolon), partly |
| [`PSR2.Methods.MethodDeclaration`](#psr2methodsmethoddeclaration) | `mago format`, [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore) |
| `PSR2.Namespaces.NamespaceDeclaration` | `mago format` |
| [`PSR2.Namespaces.UseDeclaration`](#psr2namespacesusedeclaration) | `mago format`, Mago `semantics` |

### `PSR2.Classes.PropertyDeclaration`

| Codes | Handled by |
| --- | --- |
| `AbstractAfterVisibility`, `AvizKeywordOrder`, `FinalAfterVisibility`, `ReadonlyBeforeVisibility`, `SpacingAfterType`, `StaticBeforeVisibility` | `mago format` |
| `Multiple` | [`drupal/property-per-statement`](../rules/statements.md#drupalproperty-per-statement) |
| `ScopeMissing` | [`drupal/property-visibility`](../rules/statements.md#drupalproperty-visibility) |
| `Underscore` | [`drupal/property-name`](../rules/naming-and-imports.md#drupalproperty-name) |

### `PSR2.ControlStructures.SwitchDeclaration`

| Codes | Handled by |
| --- | --- |
| `BodyOnNextLineDEFAULT`, `BreakNotNewLine`, `caseNotLower`, `defaultNotLower`, `SpaceBeforeColonCASE`, `SpaceBeforeColonDEFAULT`, `SpacingAfterCase` | `mago format` |
| `TerminatingComment` | [`drupal/case-fall-through`](../rules/statements.md#drupalcase-fall-through) |
| `WrongOpener` | Mago `no-redundant-block` |
| `WrongOpenercase`, `WrongOpenerdefault` | [`drupal/case-semicolon`](../rules/statements.md#drupalcase-semicolon), partly |

- `BreakNotNewLine`: A keyword inside an expression, as in `$x or exit();`, stays where it is. phpcs reads it as the end of the case and keeps reporting it.
- `WrongOpener`: `case 1: {`. Mago reports the braces as a redundant block.
- `WrongOpenercase`: `case 1;`. Coder also reports `case 1 ?>`, which PHP accepts. Mago reports a parse error there and leaves that switch out. The rest of the file is still checked.
- `WrongOpenerdefault`: `default;`. Coder also reports `default ?>`, which PHP accepts. Mago reports a parse error there and leaves that switch out. The rest of the file is still checked.

### `PSR2.Methods.MethodDeclaration`

| Codes | Handled by |
| --- | --- |
| `AbstractAfterVisibility`, `FinalAfterVisibility`, `StaticBeforeVisibility` | `mago format` |
| `Underscore` | [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore) |

### `PSR2.Namespaces.UseDeclaration`

| Codes | Handled by |
| --- | --- |
| `MultipleDeclarations`, `SpaceAfterLastUse`, `SpaceAfterUse` | `mago format` |
| `UseBeforeNamespace` | Mago `semantics` |

- `UseBeforeNamespace`: PHP refuses a `use` before the namespace, and Mago reports it as a semantic error.

## Squiz

| Sniff | Handled by |
| --- | --- |
| `Squiz.Arrays.ArrayBracketSpacing` | `mago format` |
| [`Squiz.Arrays.ArrayDeclaration`](#squizarraysarraydeclaration) | `mago format`, Nothing |
| [`Squiz.Classes.ClassFileName`](#squizclassesclassfilename) | Mago `file-name`, Mago `single-class-per-file`, partly |
| `Squiz.ControlStructures.ForEachLoopDeclaration` | `mago format` |
| [`Squiz.ControlStructures.ForLoopDeclaration`](#squizcontrolstructuresforloopdeclaration) | `mago format`, partly |
| [`Squiz.ControlStructures.SwitchDeclaration`](#squizcontrolstructuresswitchdeclaration) | `mago format`, [`drupal/case-label-line`](../rules/statements.md#drupalcase-label-line), [`drupal/empty-switch`](../rules/statements.md#drupalempty-switch), Mago `no-redundant-block`, [`drupal/case-break-blank-line`](../rules/statements.md#drupalcase-break-blank-line), Nothing |
| `Squiz.Functions.FunctionDeclarationArgumentSpacing` | `mago format` |
| [`Squiz.PHP.LowercasePHPFunctions`](#squizphplowercasephpfunctions) | `mago analyze` (`incorrect-function-casing`) |
| [`Squiz.PHP.NonExecutableCode`](#squizphpnonexecutablecode) | [`drupal/redundant-return`](../rules/statements.md#drupalredundant-return), `mago analyze` (`unevaluated-code`), `mago analyze` (`useless-control-flow`) |
| `Squiz.Strings.ConcatenationSpacing` | `mago format` |
| [`Squiz.WhiteSpace.FunctionSpacing`](#squizwhitespacefunctionspacing) | `mago format`, [`drupal/function-blank-line`](../rules/statements.md#drupalfunction-blank-line), partly |
| [`Squiz.WhiteSpace.OperatorSpacing`](#squizwhitespaceoperatorspacing) | `mago format`, partly |
| `Squiz.WhiteSpace.ScopeKeywordSpacing` | `mago format` |
| [`Squiz.WhiteSpace.SemicolonSpacing`](#squizwhitespacesemicolonspacing) | `mago format`, Mago `no-noop` |
| [`Squiz.WhiteSpace.SuperfluousWhitespace`](#squizwhitespacesuperfluouswhitespace) | `mago format`, [`drupal/file-start-whitespace`](../rules/files-and-tags.md#drupalfile-start-whitespace) |

### `Squiz.Arrays.ArrayDeclaration`

| Codes | Handled by |
| --- | --- |
| `CommaAfterLast`, `NoSpaceAfterComma`, `NoSpaceAfterDoubleArrow`, `NoSpaceBeforeDoubleArrow`, `SpaceAfterComma`, `SpaceAfterDoubleArrow`, `SpaceAfterKeyword`, `SpaceBeforeComma`, `SpaceBeforeDoubleArrow`, `SpaceInEmptyArray` | `mago format` |
| `KeySpecified`, `NoKeySpecified` | Nothing |

- `KeySpecified`: An array over several lines whose first entry doesn't have a key and a later one has. Not ported. Core turns the code off, and the written standard's own array example, `['hello', 'world', 'foo' => 'bar']`, mixes the two. Coder also takes the spread of an array element before a key, as in `[...$x['k'], 'k' => 1]`, for an entry without a key.
- `NoKeySpecified`: An array over several lines with an entry without a key after one with a key. Not ported. Core turns the code off, and the written standard's own array example, `['hello', 'world', 'foo' => 'bar']`, mixes the two.

### `Squiz.Classes.ClassFileName`

With `file-name` on and both rules excluding `*Test.php` and `*TestBase.php` under `tests/<dir>/`, as the [configuration](../setup/configuration.md) does and Coder's ruleset does. Mago reports at warning level, Coder at error. Core's own config runs Coder 8, whose sniff checks only the first class-like of a `.php` file.

`file-name` checks only a file with one class-like declaration. In a file with several, `single-class-per-file` reports every declaration after the first, whatever its name.

### `Squiz.ControlStructures.ForLoopDeclaration`

| Codes | Handled by |
| --- | --- |
| `NoSpaceAfterFirst`, `NoSpaceAfterSecond`, `SpacingBeforeFirst` | `mago format` |
| `SpacingAfterFirst`, `SpacingAfterSecond` | `mago format`, partly |

- `SpacingAfterFirst`: The formatter puts each part of a long `for` header on its own line, and Coder reports the line break.
- `SpacingAfterSecond`: The formatter puts each part of a long `for` header on its own line, and Coder reports the line break.

### `Squiz.ControlStructures.SwitchDeclaration`

| Codes | Handled by |
| --- | --- |
| `CaseNotLower`, `ContentAfterCase`, `ContentAfterDefault`, `ContentBeforeBreak`, `DefaultNotLower`, `SpacingAfterDefault` | `mago format` |
| `ContentBeforeCase`, `ContentBeforeDefault` | `mago format`, [`drupal/case-label-line`](../rules/statements.md#drupalcase-label-line) |
| `MissingCase` | [`drupal/empty-switch`](../rules/statements.md#drupalempty-switch) |
| `SpaceBeforeColonCase`, `SpaceBeforeColonDefault` | `mago format`, Mago `no-redundant-block` |
| `SpacingAfterBreak` | [`drupal/case-break-blank-line`](../rules/statements.md#drupalcase-break-blank-line) |
| `WrongOpenerCase`, `WrongOpenerDefault` | Nothing |

- `SpaceBeforeColonCase`: Coder also reports `case 1: {`. Mago reports the braces as a redundant block.
- `SpaceBeforeColonDefault`: Coder also reports `default: {`. Mago reports the braces as a redundant block.
- `WrongOpenerCase`: `case 1 ?>`, which PHP accepts. Mago reports a parse error there and leaves that switch out. The rest of the file is still checked.
- `WrongOpenerDefault`: `default ?>`, which PHP accepts. Mago reports a parse error there and leaves that switch out. The rest of the file is still checked.

### `Squiz.PHP.LowercasePHPFunctions`

With `check-name-casing = true`. Reported without a fix. The analyzer skips a first-class callable such as `STRLEN(...)`. It also reports a call to a function of the codebase whose case differs from the declaration, where Coder checks only PHP's own functions.

### `Squiz.PHP.NonExecutableCode`

| Codes | Handled by |
| --- | --- |
| `ReturnNotRequired` | [`drupal/redundant-return`](../rules/statements.md#drupalredundant-return) |
| `Unreachable` | `mago analyze` (`unevaluated-code`), `mago analyze` (`useless-control-flow`) |

- `Unreachable`: A `break;` after `return` is `useless-control-flow`. The analyzer also reports code after an `if` and `else` that both exit, and code after an exit on the same line, which Coder skips.

### `Squiz.WhiteSpace.FunctionSpacing`

| Codes | Handled by |
| --- | --- |
| `After` | `mago format` |
| `AfterLast`, `BeforeFirst` | `mago format`, partly |
| `Before` | `mago format`, [`drupal/function-blank-line`](../rules/statements.md#drupalfunction-blank-line), partly |

- `AfterLast`: The formatter removes the blank line between a function and the end of a block, such as `if (!function_exists())` or a loop, which Coder reports.
- `BeforeFirst`: The formatter removes the blank line between the start of a block, such as `if (!function_exists())` or a loop, and a function, which Coder reports.

### `Squiz.WhiteSpace.OperatorSpacing`

| Codes | Handled by |
| --- | --- |
| `NoSpaceAfter`, `NoSpaceBefore` | `mago format`, partly |
| `NoSpaceAfterAmp`, `NoSpaceBeforeAmp`, `SpacingAfter`, `SpacingAfterAmp`, `SpacingBefore`, `SpacingBeforeAmp` | `mago format` |

- `NoSpaceAfter`: The formatter writes `catch (A|B $e)`, which Coder reports.
- `NoSpaceBefore`: The formatter writes `catch (A|B $e)`, which Coder reports.

### `Squiz.WhiteSpace.SemicolonSpacing`

The formatter puts an empty statement after a `}`, as in `};`, on a line of its own, which Coder reports. `no-noop` removes that statement.

### `Squiz.WhiteSpace.SuperfluousWhitespace`

| Codes | Handled by |
| --- | --- |
| `EmptyLines`, `EndFile`, `EndLine` | `mago format` |
| `StartFile` | [`drupal/file-start-whitespace`](../rules/files-and-tags.md#drupalfile-start-whitespace) |

## Zend

| Sniff | Handled by |
| --- | --- |
| [`Zend.Files.ClosingTag`](#zendfilesclosingtag) | `mago format`, Mago `no-closing-tag` |

### `Zend.Files.ClosingTag`

The formatter removes a closing tag at the end of the file. When the last statement doesn't have a `;`, the tag stays and isn't reported. The formatter moves it onto that statement's line. Coder's ruleset skips `*.tpl.php` files. Mago checks them too.

## Slevomat Coding Standard

| Sniff | Handled by |
| --- | --- |
| `SlevomatCodingStandard.Classes.BackedEnumTypeSpacing` | `mago format` |
| `SlevomatCodingStandard.Commenting.ForbiddenComments` | [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment) |
| `SlevomatCodingStandard.ControlStructures.NewWithParentheses` | `mago format` |
| `SlevomatCodingStandard.ControlStructures.RequireNullCoalesceOperator` | [`drupal/null-coalesce`](../rules/statements.md#drupalnull-coalesce) |
| `SlevomatCodingStandard.Namespaces.UnusedUses` | Mago `no-redundant-use`, [`drupal/doc-type-namespace`](../rules/comment-text.md#drupaldoc-type-namespace), partly |
| `SlevomatCodingStandard.Namespaces.UseDoesNotStartWithBackslash` | [`drupal/use-leading-backslash`](../rules/naming-and-imports.md#drupaluse-leading-backslash) |
| [`SlevomatCodingStandard.Namespaces.UseFromSameNamespace`](#slevomatcodingstandardnamespacesusefromsamenamespace) | Mago `no-redundant-use` |
| `SlevomatCodingStandard.PHP.ShortList` | [`drupal/short-list`](../rules/statements.md#drupalshort-list) |
| [`SlevomatCodingStandard.TypeHints.DeclareStrictTypes`](#slevomatcodingstandardtypehintsdeclarestricttypes) | `mago format`, [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment) |
| `SlevomatCodingStandard.TypeHints.NullableTypeForNullDefaultValue` | [`drupal/explicit-nullable-param`](../rules/statements.md#drupalexplicit-nullable-param) (below PHP 8.4), Mago `explicit-nullable-param` (PHP 8.4 and later) |

### `SlevomatCodingStandard.Namespaces.UseFromSameNamespace`

Mago also reports each name in a group import and each name after the first in a comma list. Coder skips those.

### `SlevomatCodingStandard.TypeHints.DeclareStrictTypes`

| Codes | Handled by |
| --- | --- |
| `IncorrectStrictTypesFormat`, `IncorrectWhitespaceAfterDeclare` | `mago format` |
| `IncorrectWhitespaceBeforeDeclare` | `mago format`, [`drupal/file-comment`](../rules/docblock-structure.md#drupalfile-comment) |

<!-- /docs-gen -->

## Sniffs added only by core

Core's `phpcs.xml.dist` runs four Slevomat sniffs left out of Coder's rulesets, and gives
`ForbiddenComments` one more pattern. They apply to core, and to a project that copies core's
configuration.

| Sniff | Handled by | Notes |
| --- | --- | --- |
| `SlevomatCodingStandard.Commenting.ForbiddenAnnotations` | [`drupal/doc-comment`](../rules/docblock-structure.md#drupaldoc-comment), partly | Core forbids `@encode`, `@inheritDoc`, `@inheritdoc` and `@returns`. `drupal/doc-comment` reports a bare `@inheritdoc` that opens a docblock, as Coder does. Nothing reports the other tags, or a bare `@inheritdoc` below a summary. |
| `SlevomatCodingStandard.Commenting.ForbiddenComments` | Nothing | Core's extra pattern rejects `{@inheritDoc}` with a capital D. Not ported. |
| `SlevomatCodingStandard.ControlStructures.RequireShortTernaryOperator` | [`drupal/short-ternary`](../rules/statements.md#drupalshort-ternary) | The rule also reports a parenthesized condition, and operands that differ in quotes or comments. |
| `SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch` | [`drupal/non-capturing-catch`](../rules/statements.md#drupalnon-capturing-catch) | The rule skips a function that may read the variable by a name built at run time, through `compact()`, `get_defined_vars()` or a variable variable. |
| `SlevomatCodingStandard.PHP.TypeCast` | Mago `deprecated-cast`, partly | Mago reports `(integer)`, `(boolean)`, `(double)` and the other long cast names only when `php-version` is 8.5 or later, where PHP deprecates them. |
