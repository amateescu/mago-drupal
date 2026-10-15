# Coming from Coder

These pages list every sniff run by Coder 9 in its `Drupal` and `DrupalPractice` standards, and what
takes its place in a Mago setup with this extension and its
[configuration](../setup/configuration.md). To find a check, search for the sniff or the code from a
phpcs report or a `phpcs:ignore` comment. phpcs names a check `Standard.Category.Sniff.Code`.

- [Drupal standard](drupal.md): Coder's own sniffs.
- [Sniffs from other standards](other-standards.md): the sniffs taken by the `Drupal` standard from
  PHP_CodeSniffer and the Slevomat Coding Standard.
- [DrupalPractice standard](drupal-practice.md).

The **Handled by** column names one or more of:

- a rule of this extension, such as `drupal/doc-comment`. Its entry under
  [Linter rules](../rules/index.md) lists the codes ported by the rule, what its fix does, and
  where it differs from Coder;
- a rule built into Mago, such as Mago `no-empty-comment`;
- `mago format`, the formatter with the `drupal` preset;
- `mago analyze`, the analyzer;
- phpcs, for a check of a Drupal 7 `.info` file or of a `.txt` or `.md` file, which Mago doesn't
  read;
- nothing, when no tool checks it yet. The notes say why.

When a sniff's codes go to different places, the row lists the codes for each. Codes excluded by
Coder's rulesets aren't listed, and neither are codes never reported by Coder.

## What replaces phpcs

| Part of the standard | Checked by |
| --- | --- |
| Whitespace, indentation, braces, docblock star alignment | `mago format` |
| Class, interface and file naming | Mago's naming rules |
| Function aliases, unused imports | `no-alias-function`, `no-redundant-use`, enabled by default |
| Unreachable code, deprecated PHP functions, wrong `@param` and `@return` types | `mago analyze` |
| Everything specific to Drupal | This extension |

The checks of `.info.yml` and `.routing.yml` files need `yml` in Mago's extensions, as
[Install](../setup/install.md) shows. The `Drupal.InfoFiles` sniffs that aren't ported check only
the `.info` files of Drupal 7.

One check still needs phpcs: `Drupal.Files.TxtFileLineLength`, which checks the line length of
`.txt` and `.md` files. Mago reads every file as PHP, and a `<?php` example in such a file gives
parse errors.

`DrupalPractice.Objects.GlobalDrupal` reports a `\Drupal::service()` call where injection is
possible. The analyzer check
[`drupal/global-drupal-call`](../analyzer/injection.md#drupalglobal-drupal-call) ports it in part:
it leaves plain services alone.

To keep the line length check of text files, run phpcs with that sniff on those files only:

```shell
vendor/bin/phpcs --standard=Drupal --sniffs=Drupal.Files.TxtFileLineLength --extensions=txt,md web/modules/custom
```

## Differences that apply to every rule

**Suppression comments.** A phpcs suppression comment (`phpcs:ignore`, `phpcs:disable`,
`phpcs:ignoreFile`) doesn't suppress a Mago issue. A codebase that uses those comments must convert
them to `// @mago-expect lint:<code>` or to a Mago exclude. In core, `phpcs:ignoreFile` marks the
copied Doctrine annotation classes, test fixtures, the generated `ProxyClass` files and a few other
files. PHP_CodeSniffer 4, which Coder 9 runs on, no longer reads `@codingStandardsIgnoreFile`.

**Core's configuration.** Core's `phpcs.xml.dist` turns off some checks enabled by the Drupal
standard, and doesn't run some sniffs at all:

- it turns off `InvalidEndChar` and `SpacingAfter` of `Drupal.Commenting.InlineComment`,
  `LongFullStop` of `Drupal.Commenting.DocComment`, `PSR2.Methods.MethodDeclaration.Underscore`
  and `PSR2.Classes.PropertyDeclaration.Underscore`;
- it runs `MissingShort` of `Drupal.Commenting.DocComment` and `Missing` of
  `Drupal.Commenting.FunctionComment` only in some directories;
- it enables only `WrongOpenercase` of `PSR2.ControlStructures.SwitchDeclaration`;
- it doesn't run `Drupal.NamingConventions.ValidFunctionName`,
  `Drupal.Semantics.ConstantName.ConstConstantStart`, `Drupal.Attributes.ValidHookName`,
  `Drupal.InfoFiles.DependenciesArray`, `Drupal.Semantics.UnsilencedDeprecation`,
  `Generic.CodeAnalysis.UselessOverridingMethod`, `Generic.NamingConventions.TraitNameSuffix` or
  `SlevomatCodingStandard.PHP.ShortList`;
- it runs only `ExpectedException`, `ExceptionT`, `GlobalFunction`, `NamespacedDependency` and part
  of `VariableAnalysis` from the DrupalPractice standard.

The rules that port those checks are on by default for contrib and custom code, and the worker's
[`--core` argument](../setup/worker-arguments.md#checking-drupal-core) turns them off, or limits
them to the directories where core runs them. `--core` doesn't change Mago's own rules, such as
`trait-name`, which `mago.toml` sets. A project that turns other sub-codes off in its phpcs config
can turn off the matching rules with [`--disable`](../setup/worker-arguments.md#turning-rules-off).

**File types.** Mago reads only the file extensions in its `[source]` block. phpcs reads the
extensions in its `--extensions` option, `php` and `inc` when none is given. With the usual Coder
options, it also checks `.test` files, a Drupal 7 format, and `.txt` and `.md` files. When phpcs
scans a directory, it skips a file whose name starts with a dot, such as `.phpstan-baseline.php`.
Mago reads it.

## Comment whitespace and the formatter

The rules port `Drupal.Commenting.*`, whitespace included, with a fix wherever phpcbf has one,
except for a missing docblock: phpcbf inserts an empty one, and the rules only report it.
`mago format` covers some of the whitespace instead:

- It produces the result of `DocCommentStar` (a star on a docblock line that has none) and the
  star column of `DocCommentAlignment`, so those aren't ported. It doesn't touch the text after a
  star, so `drupal/doc-comment` ports the space after the star.
- It turns several blank lines into one, so `drupal/file-comment` reports only a file docblock with
  no blank line below it, and leaves `SpacingAfterComment` for more than one to the formatter.
- It sets the blank line between a comment and a closing bracket. It takes the line out, except
  before the closing brace of a class, interface, trait or enum with members, where it always
  writes one, after a comment too. Coder reports that line as `SpacingAfter`, and a fix that
  removes it would undo the formatter on every run. `drupal/inline-comment-blank-line` leaves all
  of these blank lines to the formatter, `SpacingAfterAtFunctionEnd` included, and reports the rest
  of `SpacingAfter`.

The checks read a docblock the way Coder does. A tag is a line that starts with `@`. Two kinds of
`@` lines stay text. An `@` line below another tag, indented like that tag's description, stays
part of the description. An indented `@code`, `@endcode`, `@link` or `@endlink` stays part of the
text around it. Coder reads both as tags. Only the tags at the column of the first one form the
groups looked at by the blank-line checks.

## Docblock types and the analyzer

`Drupal.Commenting.FunctionComment` compares a docblock against a function's real parameter list
and return type. Most of those checks are redundant with `mago analyze`. The analyzer reads
`@param`, `@return`, `@var` and `@throws` as authoritative types when there is no native hint, the
same way phpstan and psalm do. It already reports an `@return void` on a function that returns a
value, a function with no `return` at all, an `@param` that names an unknown parameter, and a type
name that doesn't resolve as a class, such as `Boolean`. It reads `integer` and `boolean` as `int`
and `bool` without a report, so the rules check Coder's type names themselves.
