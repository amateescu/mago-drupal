# Docblock structure

These rules read a docblock's summary, description and tag list. They do not need the declaration's
real signature, which `mago analyze` checks, see [Docblock types and the
analyzer](../coder/index.md#docblock-types-and-the-analyzer). The group has the rest of
`Drupal.Commenting.*`.

The checks read a docblock the way Coder does. A tag is any line that starts with `@`, at any
indent, and only the tags at the column of the first one form the groups that the blank-line checks
look at.

## Comment-style fixes

`drupal/class-comment`, `drupal/file-comment`, `drupal/function-comment` and
`drupal/variable-comment` report a `//` run or a `/* */` comment right above the declaration, where
a docblock belongs. Their fix turns the comment into a docblock, with an `@file` tag for a file. A
file comment that already starts with `@file` keeps it once.

The fix is potentially unsafe. PHP's reflection returns a docblock and not a comment, so
annotation discovery, PHPUnit and the analyzers start to read the text. It skips a trailing comment
of the line above, a comment that a blank line parts from the declaration, a directive, and a line
comment that holds `*/`. It also skips a comment below the attributes when a docblock sits above
them, because PHP reads only the lower of two docblocks.

## drupal/class-comment

- **Level:** error
- **Fix:** safe for the blank line. Potentially unsafe for the
  [comment style](#comment-style-fixes).
- **Ports:** `Drupal.Commenting.ClassComment`: `Missing`, `Short`, `SpacingAfter`, `WrongStyle`

A class, interface, trait or enum with no docblock, with a comment in the wrong style, or with a
summary that only repeats the name. Also a blank line between the docblock and the declaration,
which the fix removes.

A docblock tagged `@file` does not count as the class docblock. `drupal/file-comment` checks it. A
docblock above or below the attributes counts, as in Coder.

**Compared with Coder:**

- phpcbf writes an empty docblock for a class that has none, which Coder then reports as
  `DocComment.Empty`. The rule has no fix there.
- A summary that only repeats the name is matched in any case, so `bar.` above `class Bar` is
  reported. Coder's match is case-sensitive.

## drupal/deprecated-tag

- **Level:** warning
- **Fix:** safe for the punctuation after the link. Potentially unsafe for the rewrite of an old
  wording.
- **Ports:** `Drupal.Commenting.Deprecated`: `DeprecatedMissingSeeTag`, `DeprecatedPeriodAfterSeeUrl`, `DeprecatedVersionFormat`, `DeprecatedWrongSeeUrlFormat`, `IncorrectTextLayout`, `MissingExtraInfo`

A `@deprecated` tag that breaks the [deprecation grammar](https://www.drupal.org/node/2856820),
`@deprecated in %deprecation-version% and is removed from %removal-version%. %extra-info%.`, or
that has no `@see` tag after it. A version is `drupal:n.n.n`, `project:n.x-n.n` or
`project:n.n.n`, as in `drupal/deprecation-message`.

The change-record url is the first line of the `@see` tag. Coder 9 accepts
`https://www.drupal.org/node/n`, `https://www.drupal.org/project/name/issues/n` and
`https://git.drupalcode.org/project/name/-/work_items/n`. A safe fix removes the punctuation after
it.

A potentially unsafe fix rewrites an old core wording on the text's first line, such as
`in Drupal 8.5.x and will be removed before Drupal 9.0.0.`, as phpcbf does. It writes `drupal:`
versions with three parts and drops the text before `in` or `as of` and between the two versions.
Other layouts are reported without a fix, as with phpcbf.

## drupal/doc-comment

- **Level:** warning
- **Fix:** safe for most checks. The closer fix and the two-dot fix are potentially unsafe.
- **Ports:**
    - `Drupal.Commenting.DocComment`: `ContentAfterOpen`, `Empty`, `InheritDocWithoutBraces`, `LongNotCapital`, `MissingShort` (partly), `ParamGroup`, `ParamNotFirst`, `ShortFullStop`, `ShortNotCapital`, `ShortSingleLine`, `ShortStartSpace`, `SpacingAfter`, `SpacingAfterTagGroup`, `SpacingBeforeShort`, `SpacingBeforeTags`, `SpacingBetween`, `TagGroupSpacing`, `TagValueIndent`, `TagsNotGrouped`, `WrongEnd`
    - `Drupal.Commenting.DocCommentAlignment`: `NoSpaceAfterStar`, `SpaceAfterStar`
    - `SlevomatCodingStandard.Commenting.ForbiddenComments.CommentForbidden`

The summary, description, tag groups and whitespace of every docblock outside a function body. A
docblock inside a function body gets the star check and the two-dot check only. The summary and the
long description of a file docblock are the lines after `@file`, checked like any other docblock's.

The summary and the description:

- an empty docblock;
- no summary. As in Coder, the rule looks only at the first tag, or at the tag after a leading
  `@file`. `@covers` there needs no summary. A bare `@inheritdoc` there gets the braces report below
  instead. A docblock whose tag there is `@defgroup`, `@addtogroup`, `@}` or `@coversDefaultClass`
  gets only the checks on its first and last lines, the star check and the two-dot check. A file
  docblock that holds only `@file` is exempt. `{@inheritdoc}` and a summary that is the file's name
  are fine;
- a summary that does not start with an upper-case letter. As in Coder 9, a summary that starts
  with a digit, `#`, `_` or other punctuation counts, and one that starts with a multi-byte
  character does not. The fix uppercases a lower-case letter;
- a summary with no punctuation at the end. The fix adds a full stop to a one-line summary that
  ends with a letter or a digit;
- a summary that spans more than one line;
- a long description that starts with a lower-case letter. The fix uppercases it.

The tags:

- `@param` tags that are not in one group, or that are not in the first group;
- tags of one kind that are not grouped;
- `@inheritdoc` without braces in a docblock with no summary, as its first tag or the tag after a
  leading `@file`. The fix writes `{@inheritdoc}`.

The whitespace, each with a fix:

- text on the line of the opening `/**`;
- blank lines at the start or the end;
- more than one blank line between the summary and the description;
- not exactly one blank line before the tags;
- no blank line between the `@param`, `@return` and `@throws` sections and the tags next to them,
  and more than one after a section;
- not exactly one space before the summary or after a tag;
- the space after the star of a docblock line: no space, and more than one space or a tab before
  `@param`, `@return`, `@throws`, `@ingroup` or `@var`. This check covers a docblock that comes
  before a declaration keyword or right after the `<?php` tag.

The rest, each with a potentially unsafe fix, because it drops characters of the comment:

- a closer other than `*/`, such as `**/`. The fix writes `*/`;
- a line that ends in two dots. The fix removes one.

`mago format` sets the star column and adds a missing star, see [Comment whitespace and the
formatter](../coder/index.md#comment-whitespace-and-the-formatter).

**Compared with Coder:**

- Core runs `MissingShort` only in tests. `--core` does not narrow it, so the rule reports a
  missing summary in any file.
- A file docblock that holds only `@file` gets no report. Coder reports it as `MissingShort`.
- A `phpcs:` line above the summary is skipped, and the line below it is the summary. Coder
  reports `MissingShort` there.
- Text on the line of the opening `/**` is reported once. Coder also reports it as
  `SpacingBeforeShort`.
- The space after the star is checked in the docblocks that Coder's `DocCommentAlignment` picks:
  the ones whose next token is `class`, `interface`, `function`, `public`, `private`, `protected`,
  `static`, `abstract` or `var`, and the one right after the `<?php` tag. A `final class`, an enum,
  a trait, a constant or a docblock followed by an attribute is not checked.
- The space before the summary is checked in every docblock outside a function body, as
  `ShortStartSpace`, and that includes two spaces or a tab. The `@param` groups of a file docblock
  are checked too.
- The `@param` groups follow Coder: a `@param` right below an `@code`, `@todo` or `@link` tag, with
  no blank line, is reported, and so is a `@param` inside an `@code` example that sits at the
  column of the first tag.
- phpcbf has no fix for a closer. Its two-dot fix also takes the space after the opening `/**` or
  the star, and deletes a docblock that has nothing else in it. The rule turns the two dots and the
  spaces before them into one full stop, and leaves the docblock.
- Mago's `no-empty-comment` also reports an empty docblock, and its safe fix deletes it.
- The pattern in core's phpcs config that forbids `@inheritDoc` anywhere in a docblock line is not
  ported.

## drupal/file-comment

- **Level:** error
- **Fix:** safe for the `@file` tag and the blank line. Potentially unsafe for moving the tag, for
  deleting the docblock of a namespaced class file, and for the
  [comment style](#comment-style-fixes).
- **Ports:**
    - `Drupal.Commenting.FileComment`: `FileTag`, `Missing`, `NamespaceNoFileDoc`, `SpacingAfterComment`, `WrongStyle` (partly)
    - `SlevomatCodingStandard.TypeHints.DeclareStrictTypes.IncorrectWhitespaceBeforeDeclare` (partly)

A procedural file that does not start with a docblock that has the `@file` tag. This check reads
`.module`, `.install`, `.inc`, `.theme`, `.profile` and `.engine` files. A directive such as
`// phpcs:ignoreFile` above the docblock is skipped, and so is a byte order mark before the open
tag, which `drupal/byte-order-mark` reports.

It also reports:

- a docblock with no `@file` tag. The fix adds `@file` below the opener when a blank line parts the
  docblock from the code;
- no blank line between the docblock and the code. The fix adds it. `mago format` turns more than
  one into one. This covers a file docblock right above `declare`, in a procedural file;
- an `@file` tag that is not on the line right below the opener. A potentially unsafe fix moves a
  tag that stands alone on its line, when the opener is alone on its line too;
- a comment in the wrong style. A `@codingStandardsIgnoreFile` line stays a directive.

A tag that is not exactly `@file`, such as `@FILE` or `@File`, counts as no tag, as in Coder.

The checks above skip the same files as Coder. They skip a file that holds a class, interface,
trait or enum and a `namespace` statement. They also skip a file that holds exactly one class,
interface, trait or enum, no function or method outside it, and no `@file` tag in any docblock. A
closure or an arrow function does not count as a function. A method of an anonymous class outside
the class does.

A file with a `namespace` statement and exactly one class, interface, trait or enum must not start
with a comment, as in Coder. This check runs on every file that Mago reads, `.php` files included.
A file with two or more of them may start with a docblock. The fix deletes a docblock at the start,
with the whitespace after it. The text of the docblock is lost, so the fix is potentially unsafe. A
plain comment gets no fix, as in Coder.

**Compared with Coder:**

- Coder also checks a `.php` file that has no class.
- A directive at the start of the file, such as `// phpcs:disable` or `// @mago-expect`, is
  skipped, and the comment below it is checked. Coder finds no file comment after a `phpcs:`
  directive, so it reports a missing one in a procedural file and nothing in a namespaced class
  file. It takes a `@codingStandardsIgnore`, `@mago-`, `@phpstan-` or `@psalm-` comment for the
  file comment.
- phpcbf writes an empty stub for a missing docblock. The rule has no fix there.
- The report of a missing `@file` sits on the second line of the docblock, where Coder puts it, or
  on the opener when the docblock has no star line. Coder puts it on the first line of the file in
  that case.
- A blank line between the docblock and a `?>` that follows it is not reported. Coder reports it
  (`TemplateSpacingAfterComment`).

## drupal/function-comment

- **Level:** error
- **Fix:** safe for the whitespace, the type names, a `@param` type written after the variable, and
  the punctuation. Potentially unsafe for the [comment style](#comment-style-fixes).
- **Ports:** `Drupal.Commenting.FunctionComment`: `DuplicateReturn`, `EmptySees`, `IncorrectParamVarName`, `InvalidReturn`, `Missing`, `MissingParamComment`, `MissingParamName`, `MissingParamType`, `MissingReturnComment`, `MissingReturnType`, `ParamCommentFullStop`, `ParamCommentIndentation`, `ParamCommentNewLine`, `ParamCommentNotCapital`, `ParamMissingDefinition`, `ParamNameDot`, `ParamTypeSpaces`, `ReturnCommentIndentation`, `ReturnTypeSpaces`, `ReturnVarName`, `SeeAdditionalText`, `SeePunctuation`, `SpacingAfter`, `SpacingAfterParamType`, `ThrowsComment`, `ThrowsCommentIndentation`, `ThrowsNoFullStop`, `ThrowsNotCapital`, `WrongStyle`

A function or method with no docblock, or with a comment in the wrong style. A constructor with a
docblock is checked, and one with nothing above it needs no docblock, as in Coder. A docblock
tagged `@file` above a function is reported as no docblock for the function, and its tags are not
checked, as in Coder. A docblock above or below the attributes counts, as in Coder.

The rule checks the tags, with a fix where the list says so:

- `@param`: no type, no variable name and no description. A `&` or `...` in front of the variable
  belongs to the variable, so `@param &$a` has no type. On a tag with no type, a single word after
  the variable is the type and not a description, as in Coder. The fix moves that word in front of
  the variable when it is made of type characters, as in `@param $a int`. A tag with no type gets
  no capital letter or full stop check, as in Coder. A description whose first line has no
  upper-case letter anywhere, as in Coder. That line is the first one below the tag, or the text on
  the tag's line when nothing is below it. So `lower Case.` passes and `_lower.` is reported. A
  description with no full stop, unless it ends in an `@code` example, as in Coder. The fix adds
  the full stop unless the description ends in a url, a tag, or `:`, `,` or `;`. A description on
  the tag's line, which the fix moves below, and one not indented three spaces from the star, which
  the fix indents. A period after the variable name, and not exactly one space between the type and
  the variable, both fixed. A type with a space;
- a method with `@param` tags for some parameters and none for a real parameter;
- `@return`: more than one, no type on its line, and a type with a space. No description, except
  for `@return void`, `@return static` and `@return $this`. A description not indented three
  spaces from the star, and a variable name after a type that has a description below, both fixed;
- `@throws`: a description on the tag's line, which the fix moves to the line below, three spaces
  from the star. A description not indented three spaces, which the fix indents. A description
  that starts with a lower-case ASCII letter, as in Coder, or has no full stop;
- `@see`: no reference, and text after the reference. Punctuation after a one-word reference,
  which the fix removes;
- a `@param` or `@return` type name that Coder wants written another way, such as `integer` for
  `int`. The fix writes Coder's name;
- a blank line between the docblock and the function, which the fix removes.

A `@return` variable name and a `@see` reference are read from the tag's own line.

**Compared with Coder:**

- The constructor name is matched in any case, because PHP ignores case in method names. Coder
  matches `__construct` exactly and reports `__CONSTRUCT` as missing a docblock. A function named
  `__construct` outside a class is not a constructor and is reported. Coder exempts it.
- phpcbf writes an empty docblock for a function that has none, which Coder then reports as
  `DocComment.Empty`. The rule has no fix there.
- A `@param` type is the text before the variable, and a `@return` type is the text on the tag's
  line, as in Coder. Both skip a type with a bracket. A `@return` type with a space is reported
  only when a description follows below, and only when the docblock has one `@return`. A
  non-breaking space is not whitespace to Coder or to this rule.
- phpcbf moves any single word after the variable of a tag with no type, so `@param $a Done.`
  becomes `@param Done. $a`. The fix here moves only a word made of type characters. It moves the
  word as written, so `@param $a integer` becomes `@param integer $a`, and a second `--fix` run
  writes `int`.
- A bare `@return` that is the last tag is left to Mago's `valid-docblock`. A `@return 0` has a
  type here. Coder reports it, because PHP's `empty("0")` is true. A `@return` variable name is a
  type and one variable on the line, as in Coder, so `callable(int $a): int` is not one.
- For `@throws` text on the tag's line with nothing below, Coder counts the words on the line. It
  reports a type such as `\Foo2Bar` or `\A|\B` that has no text, and stops at the first `@throws`
  with no text below it. This rule reports only text after the type, and checks every `@throws`.
  Coder has no fix for that text or for the `@throws` indent. The fix here is not offered for a tag
  that shares its line with the closing `*/`.
- A `@param` line with only trailing whitespace after the variable is not reported. Coder reads
  that whitespace as a description on the tag's line.
- A `@param` indented inside an `@code` example is not a tag here. Coder reads it as one.
- A run of dots after the variable name, as in `$names...`, is reported with no fix, because it
  can be a variadic written out. phpcbf removes the dots. A name followed by a comma, as in
  `$a,...`, is not reported. Coder reports it.

## drupal/hook-comment

- **Level:** warning
- **Fix:** potentially unsafe. It replaces a description that repeats the function name.
- **Ports:** `Drupal.Commenting.HookComment`: `HookCommentFormat`, `HookParamDoc`, `HookRepeat`, `HookReturnDoc`

A hook implementation that is not documented as `Implements hook_foo().`, or that repeats the
`@param` or `@return` documentation of the hook. The rule reads functions declared at the top level
of a file, and only a docblock that touches the `function` keyword. It skips a function in a block,
in `namespace X { }` or in another function, and a docblock that has a comment, an attribute or a
`?>` between it and the `function` keyword, as Coder does.

The fix replaces a description that repeats the function name, as in `Implements my_mod_help().`,
with `Implements hook_help().`. For a hook with a placeholder it writes the function's own words,
as in `hook_node_insert()` for `hook_ENTITY_TYPE_insert()`. It applies in a procedural file, when
the function name starts with the file's machine name and an underscore.

**Compared with Coder:**

- The rule joins the lines of a short description with nothing between them, and a tag on the line
  right below joins in too. So `Implements foo()` with `.` on the next line is a repeat, and a
  `@param` right below `Implements foo().` is not.
- Trailing whitespace after the full stop does not hide a repeat. Coder misses it.
- phpcbf takes the machine name up to the first underscore of the function name, so it writes
  `hook_mod_help` for `my_mod_help` in `my_mod.module`, where the rule writes `hook_help`.

## drupal/inline-variable-comment

- **Level:** warning
- **Fix:** potentially unsafe, because the analyzers start to trust the type.
- **Ports:** `Drupal.Commenting.InlineVariableComment`: `VarInline`, `VarInlineOrder`

An inline `@var` declaration that uses `//` and not `/** */`, or that writes the variable name
before the type. A `//` or `#` comment that holds `*/`, such as a commented-out docblock, is
skipped. As in Coder, the rule also skips a comment or docblock when the first code after it, past
any other comments, is a declaration keyword such as `public`, `const`, `static` or `function`,
or `include`, `require` or their `_once` forms. A comment in the wrong style there is left to the
comment-style check of that declaration's rule. On a property, `drupal/variable-comment` reports a
`@var` tag that starts with a variable name.

One fix moves a variable name written first after the type, when the tag's own line holds a whole
type. The lines below the tag stay as they are. The other turns a comment that holds only the tag,
alone on its line, into a docblock. It skips a comment with more text than the tag, and a `//` line
inside a run of `//` lines.

## drupal/variable-comment

- **Level:** error
- **Fix:** safe for the type names and the repeated name. Potentially unsafe for a variable name
  before the type, because the analyzers start to trust the type, and for the
  [comment style](#comment-style-fixes).
- **Ports:** `Drupal.Commenting.VariableComment`: `DuplicateVar`, `EmptySees`, `EmptyVar`, `IncorrectVarType` (partly), `InlineVariableName`, `Missing`, `MissingVar`, `VarOrder`, `WrongStyle`

A class property with no docblock, or with a comment in the wrong style. A docblock above or below
the attributes counts, as in Coder, and a comment between the attributes and the property is the
property's comment. A property with a native type needs a docblock but no `@var` tag. In the
docblock, the rule reports:

- no `@var` tag on a property with no native type, more than one, and one that is not the first
  tag;
- a `@var` tag with no type, and a `@see` tag with nothing after it on its line;
- a `@var` type name that Coder wants written another way, such as `integer` for `int`, `Boolean`
  for `bool` or `NULL` for `null`;
- a `@var` tag that starts with a variable name, as in `@var $count int`;
- the property name repeated after the `@var` type on the tag's line, also after a type with
  spaces such as `array<string, int>`.

The fixes remove a property name repeated after the `@var` type, and write Coder's type name. In a
`@var` tag that starts with a variable name, a fix writes the type first when a whole type follows
the name. It drops the property's own name, and moves any other name after the type.

**Compared with Coder:**

- Coder runs the whole `@var` line, description included, through its type check, and reports a
  line with a character outside its type alphabet, such as `.`, `;`, `!` or `=`. So a description
  that contains `e.g.` or ends in a full stop is reported, and so is the type `string.`. That check
  is not ported.
- The rule reads the type apart from the description. It reports `@var integer Some description`,
  where Coder takes the whole text as the type and does not report it.
- Coder reports a `@var` tag that starts with a variable name as `IncorrectVarType`, and phpcbf
  writes `count int` for `$count int`, which is broken. The rule writes the type first.
- Coder reports a name after a type with spaces, as in `@var array<string, int> $name`, as
  `IncorrectVarType`, and phpcbf drops the `$` of the name. The rule reports it as a repeated name,
  and the fix removes it.
- The rule skips a docblock with `{@inheritdoc}` in any letter case, and reads tag names in any
  letter case. Coder skips only `{@inheritdoc}` and `{@inheritDoc}`, and does not take `@VAR` for
  `@var`.
- The fix removes a repeated name only when it is the property's own name, in a declaration of one
  property, followed by a space or the end of the line. It keeps a description after the name.
  phpcbf also removes another name, and drops the description.
