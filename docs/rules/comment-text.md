# Comment text

These rules read only a comment's text. They do not compare it against the declaration that it
documents. The group has the part of `Drupal.Commenting.*` that works that way, the `@author` ban,
and the line-length check for comments. [Comment whitespace and the
formatter](../coder/index.md#comment-whitespace-and-the-formatter) says which comment whitespace
`mago format` sets.

## drupal/author-tag

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.Commenting.AuthorTag.AuthorFound`
- **Off with `--core`**

An `@author` tag. The tag goes out of date as other people edit the file, and git already records
who wrote what.

**Compared with Coder:** the rule matches the tag in any letter case, such as `@Author`. Coder
reports only `@author`.

## drupal/comment-line-length

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.Files.LineLength.TooLong`

A comment line that is longer than 80 characters. `mago format` wraps code to its line width, but
leaves comment text as written, so the formatter cannot do this check. A deeper indent from the
formatter can push a comment past 80 characters.

The rule keeps the sniff's exemptions. All of them are for text that cannot be wrapped without
damage:

- docblock tag lines;
- `@code` examples, up to `@endcode` or the next tag;
- `// @see`-style reference lines;
- annotation values;
- the `Implements hook_foo()` and `Contains ...` lines;
- a line whose last word is too long for a line of its own, such as a URL or a long class path.

**Compared with Coder:** the rule reports a line that ends in `*/`. That includes a one-line
`/* ... */` or `/** ... */` comment, a `/* ... */` comment after code, and the last line of a
longer comment with text before the `*/`. phpcs skips these lines, because each one ends on a
whitespace token and not on a comment token. Both tools report the lines above the closer.

## drupal/doc-comment-array-syntax

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.Commenting.DocCommentLongArraySyntax.DocLongArray`

The `array()` syntax inside a docblock `@code` example. Mago does not parse the example as code, so
no other rule sees it.

**Compared with Coder:** the rule reports `array()` in a `@code` example that has no `@endcode`, up
to the end of the docblock. Coder takes such a docblock as malformed and skips the rest of it.

## drupal/doc-type-namespace

- **Level:** warning
- **Fix:** safe. It writes the fully qualified name, and removes the import when nothing else uses
  it.
- **Ports:** `SlevomatCodingStandard.Namespaces.UnusedUses.UnusedUse` (partly)

A `@param`, `@return`, `@var` or `@throws` type written as the short name of a class that only
docblocks use. The rule reads every name in the type, including generic arguments, array shape
values and callable parameters, such as `Cc` in `array<string, Cc>`. The type ends at the first
space outside brackets, so the return type in `callable(Foo): Bar` is not read. A short name whose
import the code uses too is fine.

The fix writes the fully qualified name for each such name in the type, when the whole type is on
the tag's line and the docblock is below the import, in a file with one namespace. It removes the
import when every mention of the name in the file's docblocks is rewritten.

**Compared with Coder:** Coder 9 accepts short names in docblocks, but its `UnusedUses` sniff does
not read docblocks. It reports such an import as unused, and phpcbf deletes it, which leaves the
docblock naming a class that no longer resolves. Mago counts a docblock mention as a use, so its
`no-redundant-use` reports only the imports that nothing uses, and this rule reports the ones that
only docblocks use. An import that only a `@see` tag mentions is reported by neither rule, and the
same goes for any tag other than `@param`, `@return`, `@var` and `@throws`. Coder reports it as
unused.

## drupal/expected-exception-tag

- **Level:** warning
- **Fix:** none
- **Ports:** `DrupalPractice.Commenting.ExpectedException.TagFound`

A legacy PHPUnit `@expectedException*` docblock tag. PHPUnit no longer has these tags. Use
`expectException()` and the related methods.

The rule finds the tag at the start of any docblock line, after the star and any indent. That
includes a tag indented under the description of another tag. A tag name after other text on the
line is not a tag. Coder reads both cases the same way.

**Compared with Coder:** the rule matches the tag in any letter case, such as
`@ExpectedException`. Coder reports only the exact spelling, such as `@expectedException`.

## drupal/gender-neutral-comment

- **Level:** warning
- **Fix:** none
- **Ports:** `Drupal.Commenting.GenderNeutralComment.GenderNeutral`

A gendered pronoun in a comment: he, her, hers, him, his or she, in any letter case. Each line of
a comment that holds one is reported on its own. On a docblock line the tag name is skipped and the
text after it is read, as Coder does.

## drupal/inline-comment

- **Level:** warning
- **Fix:** safe. It uppercases the first letter and sets the space after `//`. The other checks
  have no fix.
- **Ports:** `Drupal.Commenting.InlineComment`: `DocBlock`, `NoSpaceBefore`, `NotCapital`, `SpacingBefore` (partly), `TabBefore`, `WrongStyle`

A `//` comment that:

- starts with a lower-case letter;
- uses `#` and not `//`. `mago format` also writes it as `//`;
- does not have one space after `//`, or has a tab. More spaces are fine to line up with a list
  item or `@todo` on the line above. A line indented deeper than the comment line above for no such
  reason is reported without a fix, as phpcbf leaves it too.

The rule skips a `//` comment after a `}` on its line, such as `} // end if`, an `@code` example
and a `phpcs:` line. The `//` lines right below such a `}` comment are checked as a comment of
their own.

It also reports a `/**` docblock inside a body, such as a function, a class or an `if`, that does
not start with a tag and is not in front of a declaration, an enum case, an include or a modifier.
A docblock outside every body is left alone.

**Compared with Coder:**

- A tab after spaces after `//` is reported once, as a tab.
- The rule does not report an empty docblock inside a body, which Mago's `no-empty-comment`
  reports. It treats `/***` and `/**/` as no docblock, as Coder does.
- Coder reports the docblock of an enum case and of a property declared `readonly` with no
  visibility keyword, because its list of declaration keywords has neither. The rule takes both for
  declarations, as PHP does.

## drupal/inline-comment-blank-line

- **Level:** warning
- **Fix:** safe. It removes the line.
- **Ports:**
    - `Drupal.Commenting.InlineComment.SpacingAfter` (partly)
    - `DrupalPractice.Commenting.CommentEmptyLine.SpacingAfter` (partly)
- **Off with `--core`**

A blank line below a `//` comment on its own line. A `cspell:` line and a directive, such as a
`@codingStandardsIgnore*` line, are skipped.

`drupal/parameter-blank-line` also reports a blank line below a `//` comment in a parameter list,
with a fix for the same line. When both rules run in one pass, Mago skips one edit, and the next
pass applies it.

**Compared with Coder:** a blank line before a closing bracket is left to `mago format`, and that
includes Coder's `SpacingAfterAtFunctionEnd`. The formatter takes such a line out, except before
the closing brace of a class, interface, trait or enum with members, where it always writes one,
after a comment too. Coder reports that line, and a fix that removes it would undo the formatter on
every run. The rule also skips a `//` comment with no text, a comment right before a docblock and a
`// @code` example, as `Drupal.Commenting.InlineComment` does. DrupalPractice's `CommentEmptyLine`
reports all three.

## drupal/inline-comment-punctuation

- **Level:** warning
- **Fix:** safe. It appends a full stop, as phpcbf does, so a comment that ends with `,` ends with
  `,.`.
- **Ports:** `Drupal.Commenting.InlineComment.InvalidEndChar`
- **Off with `--core`**

A `//` comment that does not end with a full stop, an exclamation mark, a question mark, a colon or
a closing parenthesis. The rule skips a comment whose first word does not start with a letter, a
numbered list item, a comment with a `cspell:` line, and a last word that holds a url or a function
call such as `foo()`, or starts with `@`. It also skips a comment after a `}` on its line, such as
`} // end if`. The `//` lines right below such a comment are checked as a comment of their own.

## drupal/long-description-punctuation

- **Level:** warning
- **Fix:** safe. It adds a full stop.
- **Ports:** `Drupal.Commenting.DocComment.LongFullStop`
- **Off with `--core`**

A docblock long description that ends with a letter.

## drupal/post-statement-comment

- **Level:** warning
- **Fix:** safe. It moves the comment to its own line above.
- **Ports:** `Drupal.Commenting.PostStatementComment.Found`

A `//` comment on the same line as the statement before it.

There is no fix where the move could attach the comment to something else:

- a line that opens a block or closes a construct;
- a line below a docblock or another comment, such as an `@phpstan-ignore` for the statement;
- a line inside a string;
- a comment that the next line continues;
- a comment that applies to one line, such as `cspell:disable-line` or `@codeCoverageIgnore`.

**Compared with Coder:** the rule skips a trailing `@mago-`, `@phpstan-`, `@psalm-` or
`@codingStandardsIgnore` comment as well as a `phpcs:` one, because a tool pragma works only on the
line of its statement. Coder skips only `phpcs:`.

## drupal/todo-comment

- **Level:** warning
- **Fix:** safe. It writes `@todo ` in place of the spelling and the dashes, colons and spaces after
  it.
- **Ports:** `Drupal.Commenting.TodoComment.TodoFormat`

A to-do comment that does not follow the [`@todo Fix problem X here.`](https://www.drupal.org/node/1354)
format, such as `TODO:`, `@TODO`, `to-do` or `@todo - fix`. The rule skips a to-do with no text,
and a word that only starts with "todo", such as "todos".

Mago's own `tagged-todo` rule asks for a `TODO(@user)` or `TODO(#123)` reference. That format
contradicts Drupal's, so do not run the two rules on the same codebase.
[Setup](../setup.md#turn-off-what-drupals-standard-does-not-ask-for) turns `tagged-todo` off.
