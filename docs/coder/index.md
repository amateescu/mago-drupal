# Coming from Coder

These pages list every sniff that Coder 9 runs in its `Drupal` and `DrupalPractice` standards, and
say what takes its place in a Mago setup with this extension and the configuration from
[Setup](../setup.md). phpcs names a check `Standard.Category.Sniff.Code`. Search for the sniff or
the code from a phpcs report or a `phpcs:ignore` comment.

- [Drupal standard](drupal.md): Coder's own sniffs.
- [Sniffs from other standards](other-standards.md): the sniffs that the `Drupal` standard takes
  from PHP_CodeSniffer, the Slevomat Coding Standard and VariableAnalysis.
- [DrupalPractice standard](drupal-practice.md).

The **Handled by** column names one or more of:

- a rule of this extension, such as `drupal/doc-comment`. Its entry under [Rules](../rules/index.md)
  lists the codes it ports, what its fix does, and where it differs from Coder;
- a rule built into Mago, such as Mago `no-empty-comment`;
- `mago format`, the formatter with the `drupal` preset;
- `mago analyze`, the analyzer;
- phpcs, for a check of a YAML, `.info` or text file, which Mago does not read;
- nothing, when no tool checks it yet. The notes say why.

When a sniff's codes go to different places, the row lists the codes for each. Codes that Coder's
rulesets exclude are not listed, and neither are codes that Coder never reports.

## What replaces phpcs

| Part of the standard | Checked by |
| --- | --- |
| Whitespace, indentation, braces, code line width, docblock star alignment | `mago format` |
| Class, interface and file naming | Mago's naming rules |
| Function aliases, unused imports | `no-alias-function`, `no-redundant-use`, enabled by default |
| Unreachable code, deprecated PHP functions, wrong `@param` and `@return` types | `mago analyze` |
| Everything specific to Drupal | This extension |

The checks of `.info.yml` and `.routing.yml` files need `yml` in Mago's extensions, as
[Setup](../setup.md#install) shows. The `Drupal.InfoFiles` sniffs that are not ported check only the
`.info` files of Drupal 7.

Two checks still need phpcs:

- `Drupal.Files.TxtFileLineLength` checks the line length of `.txt` and `.md` files. Mago reads every
  file as PHP, and a `<?php` example in such a file gives parse errors.
- `DrupalPractice.Objects.GlobalDrupal` reports a `\Drupal::service()` call where injection is
  possible. That check needs the analyzer, not a linter rule.

## Differences that apply to every rule

**Suppression comments.** A phpcs suppression comment (`phpcs:ignore`, `phpcs:disable`,
`phpcs:ignoreFile`, `@codingStandardsIgnoreFile`) does not suppress a Mago issue. A codebase that
uses those comments must convert them to `// @mago-expect lint:<code>` or to a Mago exclude. Core's
generated `ProxyClass` files are the most frequent case.

**Core's configuration.** Core's `phpcs.xml.dist` turns off some checks that the Drupal standard
enables, and does not run some sniffs at all:

- it turns off `InvalidEndChar` and `SpacingAfter` of `Drupal.Commenting.InlineComment`,
  `LongFullStop` of `Drupal.Commenting.DocComment` and `PSR2.Methods.MethodDeclaration.Underscore`;
- it enables only `WrongOpenercase` of `PSR2.ControlStructures.SwitchDeclaration`;
- it does not run `Drupal.NamingConventions.ValidFunctionName`,
  `Drupal.Semantics.ConstantName.ConstConstantStart`, `Drupal.Attributes.ValidHookName` or
  `SlevomatCodingStandard.PHP.ShortList`;
- it runs only `ExpectedException`, `ExceptionT`, `GlobalFunction`, `NamespacedDependency` and part
  of `VariableAnalysis` from the DrupalPractice standard.

The rules that port those checks are on by default for contrib and custom code, and the worker's
[`--core` argument](../setup.md#checking-drupal-core) turns them off. A project that turns other sub-codes off in
its phpcs config can turn off the matching rules with [`--disable`](../setup.md#turning-rules-off).

**File types.** Mago reads only the file extensions in its `[source]` block. Coder also checks
`.test` files, a Drupal 7 format, and `.txt` and `.md` files.

## Comment whitespace and the formatter

The rules port `Drupal.Commenting.*`, whitespace included, with a fix wherever phpcbf has one.
`mago format` covers some of the whitespace instead:

- It produces the result of `DocCommentStar` (a star on a docblock line that has none) and the
  star column of `DocCommentAlignment`, so those are not ported. It does not touch the text after a
  star, so `drupal/doc-comment` ports the space after the star.
- It turns several blank lines into one, so `drupal/file-comment` reports only a file docblock with
  no blank line below it, and leaves `SpacingAfterComment` for more than one to the formatter.
- It sets the blank line between a comment and a closing bracket. It takes the line out, except
  before the closing brace of a class, interface, trait or enum with members, where it always
  writes one, after a comment too. Coder reports that line as `SpacingAfter`, and a fix that
  removes it would undo the formatter on every run. `drupal/inline-comment-blank-line` leaves all
  of these blank lines to the formatter, `SpacingAfterAtFunctionEnd` included, and reports the rest
  of `SpacingAfter`.

The checks read a docblock the way Coder does. A tag is any line that starts with `@`, at any
indent, and only the tags at the column of the first one form the groups that the blank-line
checks look at.

## Docblock types and the analyzer

`Drupal.Commenting.FunctionComment` compares a docblock against a function's real parameter list
and return type. Most of those checks are redundant with `mago analyze`. The analyzer reads
`@param`, `@return`, `@var` and `@throws` as authoritative types when there is no native hint, the
same way phpstan and psalm do. It already reports an `@return void` on a function that returns a
value, a function with no `return` at all, an `@param` that names an unknown parameter, and a type
name that does not resolve as a class, such as `Boolean`. It reads `integer` and `boolean` as `int`
and `bool` without a report, so the rules check Coder's type names themselves.
