# phpcs ignores

With the `--phpcs-ignores` worker argument, the extension reads phpcs suppression comments. A
comment drops the issues of the rules and analyzer checks that port the Coder codes it ignores, on
the lines where phpcs ignores them. A project that moves from phpcs keeps its comments, and doesn't
need a `@mago-expect` next to each one.

```php
// phpcs:ignore Drupal.NamingConventions.ValidVariableName.LowerCamelName
public $current_page = NULL;  // no drupal/property-name
```

## Turning it on

It's off by default. Add `"--phpcs-ignores"` to the worker command in `mago.toml`. The command
replaces the one of `drupal.mago.toml` as a whole, so the line repeats the rest of it:

```toml
[extension-hosts.drupal]
command = ["php", "-d", "xdebug.mode=off", "-d", "pcov.enabled=0", "vendor/amateescu/mago-drupal/resources/worker.php", "--phpcs-ignores"]
```

`drupal-core.mago.toml` turns it on, since core's own phpcs run relies on these comments. See
[Worker arguments](../setup/worker-arguments.md) for the other arguments.

## Comment forms

The extension reads the comments the way PHP_CodeSniffer 3 does:

- `phpcs:ignore`. On its own line it covers the next line, and after code it covers its own line;
- `phpcs:disable` and `phpcs:enable`, which cover the lines between them. A `phpcs:enable` that
  names codes turns them back on inside the region, also when the region names their sniff or
  standard;
- `phpcs:ignoreFile`, anywhere in the file, which covers the whole file;
- the older `@codingStandardsIgnoreLine`, `@codingStandardsIgnoreStart`,
  `@codingStandardsIgnoreEnd` and `@codingStandardsIgnoreFile`.

A comment can be a `//`, `#` or `/* */` comment, or a line of a docblock, such as
`* phpcs:ignore Drupal.Files.LineLength`. The `phpcs:` forms can start with an `@`, as in
`@phpcs:ignore`, in any case. A note after ` --` isn't part of the codes.

The line that a comment covers is the one phpcs picks. A `phpcs:ignore` covers the next line even
when that line is blank. One in the last line of a docblock covers the `*/` line, where Coder
reports some docblock codes, such as a missing `@var` tag. The old forms count the star of a
docblock as code before the comment, so `* @codingStandardsIgnoreLine` covers only its own line.

PHP_CodeSniffer 4, which Coder 9 runs on, no longer reads the `@codingStandards` forms. The
extension reads them, since core runs Coder 8 on PHP_CodeSniffer 3, and some of its files use them.

## Which issues a comment drops

A comment names codes separated by commas. Each one is a full code, such as
`Drupal.Semantics.FunctionT.NotLiteralString`, or a sniff, a category or a standard, such as
`Drupal.Semantics.FunctionT` or `Drupal`. The comment drops the issue of a rule when it names a code
that the rule ports, or the sniff, category or standard of one. The entries of the
[linter rules](../rules/index.md) list the codes that each rule ports.

A comment without codes drops the issues of every rule of the extension on its lines, also the rules
that don't port a Coder code. `phpcs:ignoreFile` does the same for the whole file.

A rule that ports several codes doesn't tell which one an issue is for. A comment that names any of
them drops the issue, so `phpcs:ignore Drupal.Commenting.DocComment.ShortFullStop` also drops a
`drupal/doc-sentence` issue for a long description on that line.

An issue counts as on the line where it starts. The rules start an issue on the line where Coder
reports it, so a comment written for Coder covers it. A rule that reports a docblock as a whole
starts the issue on the docblock's first line.

The extension also reads the names of the Coder 8 sniffs that Coder 9 replaced, such as
`DrupalPractice.CodeAnalysis.VariableAnalysis`, and of the sniffs that core's `phpcs.xml.dist` adds
to Coder's standards:

<!-- docs-gen:phpcs-other-names -->

- [`drupal/case-semicolon`](../rules/statements.md#drupalcase-semicolon): `Drupal.ControlStructures.CaseSemicolon.SemicolonNotAllowed`
- [`drupal/doc-type-namespace`](../rules/comment-text.md#drupaldoc-type-namespace): `Drupal.Classes.UnusedUseStatement.UnusedUse`, `Drupal.Commenting.DataTypeNamespace.DataTypeNamespace`
- [`drupal/else-if`](../rules/statements.md#drupalelse-if): `Drupal.ControlStructures.ElseIf.ElseIfDeclaration`
- [`drupal/method-name-underscore`](../rules/naming-and-imports.md#drupalmethod-name-underscore): `Drupal.Methods.MethodDeclaration.Underscore`
- [`drupal/non-capturing-catch`](../rules/statements.md#drupalnon-capturing-catch): `SlevomatCodingStandard.Exceptions.RequireNonCapturingCatch.NonCapturingCatchRequired`
- [`drupal/short-ternary`](../rules/statements.md#drupalshort-ternary): `SlevomatCodingStandard.ControlStructures.RequireShortTernaryOperator.RequiredShortTernaryOperator`
- [`drupal/unused-variable`](../rules/statements.md#drupalunused-variable): `DrupalPractice.CodeAnalysis.VariableAnalysis.UnusedVariable`
- [`drupal/use-leading-backslash`](../rules/naming-and-imports.md#drupaluse-leading-backslash): `Drupal.Classes.UseLeadingBackslash.SeparatorStart`
- [`drupal/variable-redeclaration`](../rules/statements.md#drupalvariable-redeclaration): `DrupalPractice.CodeAnalysis.VariableAnalysis.VariableRedeclaration`

<!-- /docs-gen -->

## Analyzer checks

The analyzer checks that port a Coder sniff read the comments too:
[`drupal/global-drupal-call`](../analyzer/injection.md#drupalglobal-drupal-call),
[`drupal/static-entity-load`](../analyzer/injection.md#drupalstatic-entity-load) and
[`drupal/untranslated-message`](../analyzer/translation.md#drupaluntranslated-message).

## Not covered

- Mago's own rules, such as `no-redundant-use`. The linter doesn't let an extension drop their
  issues. A line that phpcs ignores for such a check needs a `@mago-expect lint:<code>` or an
  `exclude` of the rule. For the same reason, `drupal-core.mago.toml` still leaves the files marked
  with `phpcs:ignoreFile` out of the linter.
- `mago format`, which doesn't read phpcs comments.
- Mago's own analyzer issues. The [`phpstan-ignores` plugin](../analyzer/phpstan-ignores.md) reads
  `@phpstan-ignore` comments for those.
- `phpcs:set`, which changes a sniff's setting in phpcs. The extension only covers the line of the
  comment, as phpcs does.

A comment that doesn't drop anything isn't reported, as phpcs doesn't report it either.

## Pragmas

The comments drop an issue before Mago sees it. A `@mago-expect` for an issue that a phpcs comment
drops doesn't have an issue to match, and Mago reports it as unused.
