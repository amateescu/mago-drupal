# Docblock structure

These rules check the docblocks asked for in Drupal's
[API documentation and comment standards](https://project.pages.drupalcode.org/coding_standards/php/documentation/):
a docblock on every file, class, function and property, its summary and description, and its tags.
They don't check the types in a docblock against the real signature, which is done by
`mago analyze`, see
[Docblock types and the analyzer](../coder/index.md#docblock-types-and-the-analyzer).

The rules read a docblock the way Coder does. A tag is a line that starts with `@`. Two kinds of
`@` lines stay text. An `@` line below another tag, indented like that tag's description, stays part
of the description. An indented `@code`, `@endcode`, `@link` or `@endlink` stays part of the text
around it. Coder reads both as tags. Only the tags at the column of the first one form the groups
looked at by the blank-line checks.

A declaration with no docblock gets the report of its `missing-*` rule, and one with a comment in
the wrong style the report of `drupal/class-comment`, `drupal/file-comment`,
`drupal/function-comment` or `drupal/variable-comment`. The rules that check the tags, their
wording and their layout skip such a declaration, as in Coder. They also skip the tags of a
docblock tagged `@file` above a function, which counts as no docblock for the function.

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

---

## drupal/class-comment

- **Level:** error
- **Fix:** safe for the blank line. Potentially unsafe for the
  [comment style](#comment-style-fixes).
- **Standard:** [General considerations for API module parsing](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-considerations-for-api-module-parsing),
  [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation)
- **Ports:** `Drupal.Commenting.ClassComment`: `Short`, `SpacingAfter`, `WrongStyle`

> To document a function, class, etc., the docblock must appear directly before the item being
> documented, with no blank line in between (see example above).
>
> All summaries (the first line of a docblock) must be under 80 characters, start with a capital
> letter, and end with a period (.). They must provide a brief description of what a function does,
> what a class does, what a file contains, etc.

**Reports**, on a class, interface, trait or enum:

- a comment in the wrong style where the docblock belongs;
- a summary that only repeats the name. The name is matched in any case, so `bar.` above
  `class Bar` is reported;
- a blank line between the docblock and the declaration, which is removed by the fix.

A docblock above or below the attributes counts. A docblock tagged `@file` is the file's, and
[`drupal/missing-class-comment`](#drupalmissing-class-comment) reports the class as undocumented.

??? example "Example"

    ```php
    // Reported.
    /**
     * Example.
     */
    class Example {
    }

    // Fine.
    /**
     * Provides an example of a documented class.
     */
    class Example {
    }
    ```

??? note "Compared with Coder"

    - Coder matches the repeated name with case, so it doesn't report `bar.` above `class Bar`.
    - Coder reports a summary that repeats the name as a warning. The rule reports it at error
      level.

---

## drupal/deprecated-tag

- **Level:** warning
- **Fix:** safe for the punctuation after the link. Potentially unsafe for the rewrite of an old
  wording.
- **Standard:** [@deprecated](https://project.pages.drupalcode.org/coding_standards/php/documentation/#deprecated-indicating-deprecated-functionality)
- **Ports:** `Drupal.Commenting.Deprecated`: `DeprecatedMissingSeeTag`, `DeprecatedPeriodAfterSeeUrl`, `DeprecatedVersionFormat`, `DeprecatedWrongSeeUrlFormat`, `IncorrectTextLayout`, `MissingExtraInfo`

> The `@deprecated` tag is placed in a documentation block to indicate that a function, method, or
> class has been deprecated and should not be used, but has not yet been removed.
>
> ```php
> @deprecated in %deprecation-version% and is removed from %removal-version%. %extra-info%.
> @see %cr-link%
> ```

**Reports:**

- a `@deprecated` tag whose text breaks that grammar. A version is `drupal:n.n.n`,
  `project:n.x-n.n` or `project:n.n.n`, with an optional release label such as `-alpha1`, as in
  `drupal/deprecation-message`. The text may wrap over several lines;
- a `@deprecated` tag with no `@see` tag after it;
- a change-record url in another form, or with punctuation after it. The url is the first line of
  the `@see` tag. Coder 9 accepts `https://www.drupal.org/node/n`,
  `https://www.drupal.org/project/name/issues/n` and
  `https://git.drupalcode.org/project/name/-/work_items/n`.

A safe fix removes the punctuation after the url.

A potentially unsafe fix rewrites an old core wording on the text's first line, such as
`in Drupal 8.5.x and will be removed before Drupal 9.0.0.`, as phpcbf does. It writes `drupal:`
versions with three parts and drops the text before `in` or `as of` and between the two versions.
Other layouts are reported without a fix.

??? example "Example"

    ```php
    // Reported.
    /**
     * Returns the label.
     *
     * @deprecated in example:1.1.0 and is removed from example:2.0.0. Use
     *   example_title() instead.
     */
    function example_label(): string {
      return '';
    }

    // Fine.
    /**
     * Returns the label.
     *
     * @deprecated in example:1.1.0 and is removed from example:2.0.0. Use
     *   example_title() instead.
     *
     * @see https://www.drupal.org/node/3123456
     */
    function example_label(): string {
      return '';
    }
    ```

??? note "Compared with Coder"

    - Coder reports a missing `@see`, punctuation after the url, a wrong layout and missing extra
      info as errors. The rule reports every check at warning level.

---

## drupal/doc-comment

- **Level:** error
- **Fix:** safe for most checks. The closer fix and the two-dot fix are potentially unsafe.
- **Standard:** [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation),
  [{@inheritdoc}](https://project.pages.drupalcode.org/coding_standards/php/documentation/#inheritdoc-documentation-inheritance)
- **Ports:**
    - `Drupal.Commenting.DocComment`: `ContentAfterOpen`, `InheritDocWithoutBraces`, `ShortStartSpace`, `SpacingAfter`, `SpacingBeforeShort`, `SpacingBetween`, `TagValueIndent`, `WrongEnd`
    - `Drupal.Commenting.DocCommentAlignment`: `NoSpaceAfterStar`, `SpaceAfterStar`
    - `SlevomatCodingStandard.Commenting.ForbiddenComments.CommentForbidden`

> Put a space between the comment character (the `*` or the `//`) and the first letter of the
> sentence.
>
> Note the {} around the @inheritdoc tag.

The rule checks the whitespace and the edges of every docblock outside a function body. A docblock
inside a function body gets the star check and the two-dot check only.

**Reports**, each with a fix:

- text on the line of the opening `/**`;
- blank lines at the start or the end;
- more than one blank line between the summary and the description;
- not exactly one space before the summary or after a tag;
- the space after the star of a docblock line: no space, and more than one space or a tab before
  `@param`, `@return`, `@throws`, `@ingroup` or `@var`. This check covers a docblock that comes
  before a declaration keyword or right after the `<?php` tag;
- `@inheritdoc` without braces in a docblock with no summary, as its first tag or the tag after a
  leading `@file`. The fix writes `{@inheritdoc}`.

The rest, each with a potentially unsafe fix, because it drops characters of the comment:

- a closer other than `*/`, such as `**/`. The fix writes `*/`;
- a line that ends in two dots. The fix removes one.

**Not reported:**

- in a docblock whose first tag, or the tag after a leading `@file`, is `@defgroup`, `@addtogroup`,
  `@}` or `@coversDefaultClass`, everything but the checks on its first and last lines, the star
  check and the two-dot check;
- an empty docblock. Mago's `no-empty-comment` reports it at note level, and its safe fix deletes
  the docblock.

`mago format` sets the star column and adds a missing star, see [Comment whitespace and the
formatter](../coder/index.md#comment-whitespace-and-the-formatter).

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported: the text on the opening line, and the blank line at the end.
    /** Returns the label.
     *
     */
    function example_label(): string {
      return '';
    }

    // Fine.
    /**
     * Returns the label.
     */
    function example_label(): string {
      return '';
    }
    ```

??? note "Compared with Coder"

    - Text on the line of the opening `/**` is reported once. Coder also reports it as
      `SpacingBeforeShort`.
    - The space after the star is checked in the docblocks picked by Coder's `DocCommentAlignment`:
      the ones whose next token is `class`, `interface`, `function`, `public`, `private`,
      `protected`, `static`, `abstract` or `var`, and the one right after the `<?php` tag. A
      `final class`, an enum, a trait, a constant or a docblock followed by an attribute isn't
      checked.
    - The space before the summary is checked in every docblock outside a function body, as
      `ShortStartSpace`, and that includes two spaces or a tab.
    - A fix for a closer doesn't exist in phpcbf. Its two-dot fix removes both dots. Later passes
      put a full stop back on a summary and on the last line of a long description, so a middle
      line of a long description loses its full stop, and a docblock with nothing but the dots ends
      up empty. The rule turns the two dots and the spaces before them into one full stop, and
      leaves the docblock.
    - Coder reports an empty docblock as `DocComment.Empty`, at error level.
    - The pattern in core's phpcs config that forbids `@inheritDoc` anywhere in a docblock line
      isn't ported.

---

## drupal/doc-see-tag

- **Level:** error
- **Fix:** safe. It removes the punctuation after a one-word reference.
- **Standard:** [@see](https://project.pages.drupalcode.org/coding_standards/php/documentation/#see-see-also-references)
- **Ports:**
    - `Drupal.Commenting.FunctionComment`: `EmptySees`, `SeeAdditionalText`, `SeePunctuation`
    - `Drupal.Commenting.VariableComment.EmptySees`

> Each `@see` reference is on its own line, with no additional text beyond the item being
> referenced.

**Reports:**

- a `@see` tag with no reference on its line, in the docblock of a function, a method or a
  property;
- a `@see` reference with other text after it, or with punctuation at its end, in any docblock
  outside a function body. The standard asks this of every `@see` tag, and Coder reads only those
  of functions and methods.

The reference is read from the tag's own line. The lines below it are a description, which may end
in a period. The fix is left out for a `@see` after `@deprecated`, whose change-record url is
fixed by [`drupal/deprecated-tag`](#drupaldeprecated-tag).

**Not reported:**

- the references in a docblock whose first tag, or the tag after a leading `@file`, is
  `@defgroup`, `@addtogroup`, `@}` or `@coversDefaultClass`, unless it is a function's or a
  method's;
- a `@see` tag with no reference in a property docblock with `{@inheritdoc}`;
- with `--core`, the references in a docblock that isn't a function's or a method's, as in core's
  phpcs.

---

## drupal/doc-sentence

- **Level:** error
- **Fix:** safe. It uppercases a lower-case first letter of the summary and the long description,
  and adds a full stop to the summary and to a `@param` description.
- **Standard:** [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation),
  [@throws](https://project.pages.drupalcode.org/coding_standards/php/documentation/#throws-thrown-exceptions)
- **Ports:**
    - `Drupal.Commenting.DocComment`: `LongNotCapital`, `ShortFullStop`, `ShortNotCapital`, `ShortSingleLine`
    - `Drupal.Commenting.FunctionComment`: `ParamCommentFullStop`, `ParamCommentNotCapital`, `ThrowsNoFullStop`, `ThrowsNotCapital`

> All summaries (the first line of a docblock) must be under 80 characters, start with a capital
> letter, and end with a period (.).
>
> If you include an explanation, like other documentation use complete sentences that stand alone,
> without assuming that "Throws [exception name]" is part of the sentence automatically, since
> Throws will be a heading when it's displayed on api.drupal.org.

The rule checks the summary and the long description of every docblock outside a function body,
and the `@param` and `@throws` descriptions of a function or method. The summary and the long
description of a file docblock are the lines after `@file`, checked like any other docblock's.

**Reports**, in the summary and the long description:

- a summary that doesn't start with an upper-case letter. A summary that starts with a digit, `#`,
  `_` or other punctuation counts, and one that starts with a multi-byte character doesn't. The fix
  uppercases a lower-case letter;
- a summary with no punctuation at the end. The standard asks for a period, but as in Coder, `!`,
  `?` and `)` pass too. The fix adds a period to a one-line summary that ends with a letter or a
  digit;
- a summary that spans more than one line;
- a long description that starts with a lower-case letter. The fix uppercases it.

In the tags:

- a `@param` description whose first line has no upper-case letter anywhere. That line is the first
  one below the tag, or the text on the tag's line when nothing is below it. So `lower Case.`
  passes and `_lower.` is reported;
- a `@param` description with no full stop, unless it ends in an `@code` example. The fix adds the
  full stop unless the description ends in a url, a tag, or `:`, `,` or `;`;
- a `@throws` description that starts with a lower-case ASCII letter, or has no full stop.

[`drupal/long-description-punctuation`](comment-text.md#drupallong-description-punctuation) checks
the end of the long description.

**Not reported:**

- `{@inheritdoc}` in place of a summary, and a summary that is the file's name;
- a `@param` with no type, which gets no capital letter or full stop check;
- a docblock whose first tag, or the tag after a leading `@file`, is `@defgroup`, `@addtogroup`,
  `@}` or `@coversDefaultClass`.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported.
    /**
     * returns the label
     */
    function example_label(): string {
      return '';
    }

    // Fine.
    /**
     * Returns the label.
     */
    function example_label(): string {
      return '';
    }
    ```

??? note "Compared with Coder"

    - Coder reads a line that starts with an `@` word as a tag, at any indent. When no tag at the
      `@param`'s column follows, Coder ends the description at the last such line indented deeper
      than the description, and checks the full stop on the line above it. It does the same when
      the description's last line holds only an `@` word. In core's `batch_set()` docblock, the
      `progress_message` item has such a line, `@total.')".`. This rule reads every line indented
      under the tag as part of the description.
    - Coder reads a `@throws` description from the lines below the tag only. This rule starts at
      the text on the tag's line, when there is some. So `@throws \Exception Some text` with
      `and more below.` on the next line is reported by Coder as `ThrowsNotCapital`, and isn't
      reported here. A description on the tag's line only gets just `ThrowsComment` from Coder,
      which [`drupal/doc-tag-layout`](#drupaldoc-tag-layout) ports. This rule also checks its
      capital letter and full stop.

---

## drupal/doc-tag-descriptions

- **Level:** error
- **Fix:** none
- **Standard:** [Functions](https://project.pages.drupalcode.org/coding_standards/php/documentation/#functions)
- **Ports:** `Drupal.Commenting.FunctionComment`: `MissingParamComment`, `MissingReturnComment`, `ParamMissingDefinition`, `ParamNameNoMatch`

> Each parameter of a function must be documented with a `@param` tag (see exceptions below).

**Reports**, in the docblock of a function or method:

- a `@param` with no description. On a tag with no type, a single word after the variable is the
  type and not a description, so the description is the lines below;
- a `@return` with a type and no description on the lines below it. As in Coder, text on the tag's
  own line is part of the type, so `@return string The label.` and `@return array<int, string>`
  with nothing below are reported too, with the description asked for on the line below;
- a `@param` with a type that repeats an earlier tag, or that comes after the tag of a later
  parameter. As in Coder, the tags must follow the order of the parameters, and a parameter can be
  left out;
- a method with `@param` tags for some parameters and none for a real parameter. So in a method, a
  repeated tag that takes the place of another parameter's tag gets two reports. In a function it
  gets one, as in Coder.

**Not reported:**

- no description on `@return void`, `@return static` or `@return $this`;
- a `@return` with a variable name after its type, which is reported by
  [`drupal/doc-tag-layout`](#drupaldoc-tag-layout);
- the order of a `@param` with no type, which is reported by
  [`drupal/doc-tag-types`](#drupaldoc-tag-types);
- a `@param` that names no parameter, or a parameter in another case. `mago analyze` reports it as
  `invalid-param-tag`.

The standard lets a `@param` or `@return` description be left out when the type says it all, such
as a single class type that can't be null, and asks for no `@return` when nothing is returned.
Coder, core and this rule still want the description. Coder 9 and this rule accept `@return void`
without one. Core's phpcs runs Coder 8, which reports it.

??? example "Example"

    ```php
    // Reported.
    /**
     * Builds the label.
     *
     * @param int $id
     *   The ID.
     *
     * @return string
     */
    function example_label(int $id): string {
      return (string) $id;
    }

    // Fine.
    /**
     * Builds the label.
     *
     * @param int $id
     *   The ID.
     *
     * @return string
     *   The label.
     */
    function example_label(int $id): string {
      return (string) $id;
    }
    ```

??? note "Compared with Coder"

    - Coder reports a repeated tag that sits past the number of parameters, such as the third
      `@param int $a` of a function with one parameter, as `ExtraParamComment`. This rule reports it
      as a repeated tag.
    - Coder reports `@param int &$a` for a parameter that isn't a reference, and `...$a` for one
      that isn't variadic, as a name that doesn't match. This rule leaves those alone.

---

## drupal/doc-tag-groups

- **Level:** error
- **Fix:** safe for the blank lines.
- **Standard:** [Order of documentation sections](https://project.pages.drupalcode.org/coding_standards/php/documentation/#order-of-documentation-sections)
- **Ports:** `Drupal.Commenting.DocComment`: `ParamGroup`, `ParamNotFirst`, `SpacingAfterTagGroup`, `SpacingBeforeTags`, `TagGroupSpacing`, `TagsNotGrouped`

> Separate different-type sections by a blank line (for instance, all the `@param` documentation
> goes together, with a blank line before the first parameter and a blank line after the last
> parameter before the `@return` section starts).

The rule checks the docblocks outside a function body. As in Coder, it checks the `@param` group
and the blank lines only in a docblock that starts with a summary, or with `@file` and one.

**Reports:**

- `@param` tags that aren't in one group, or that aren't in the first group;
- tags of one kind that aren't grouped. Only `@param`, `@return` and `@throws` count here;
- not exactly one blank line before the tags. The fix adds or removes the blank lines;
- no blank line between the `@param`, `@return` and `@throws` sections and the tags next to them,
  and more than one after a section. The fix adds or removes the blank lines.

**Not reported:**

- no blank line before the tags when the first one is `@code`, `@link` or `@endlink`, or a
  `phpcs:` line comes right before it;
- a `@param` group that isn't first in a docblock that starts with `@code`, `@todo`, `@link`,
  `@endlink` or `@codingStandardsIgnoreStart`;
- a docblock whose first tag, or the tag after a leading `@file`, is `@defgroup`, `@addtogroup`,
  `@}` or `@coversDefaultClass`.

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported: the blank lines around the @param section.
    /**
     * Returns the label.
     * @param string $id
     *   The ID.
     * @return string
     *   The label.
     */
    function example_label(string $id): string {
      return $id;
    }

    // Fine.
    /**
     * Returns the label.
     *
     * @param string $id
     *   The ID.
     *
     * @return string
     *   The label.
     */
    function example_label(string $id): string {
      return $id;
    }
    ```

??? note "Compared with Coder"

    - The `@param` groups follow Coder: a `@param` right below an `@code`, `@todo` or `@link` tag,
      with no blank line, is reported, and so is a `@param` inside an `@code` example that sits at
      the column of the first tag. The `@param` groups of a file docblock are checked too.

---

## drupal/doc-tag-layout

- **Level:** error
- **Fix:** safe. A type with spaces has none.
- **Standard:** [@param](https://project.pages.drupalcode.org/coding_standards/php/documentation/#param-function-parameters),
  [@return](https://project.pages.drupalcode.org/coding_standards/php/documentation/#return-function-return-values),
  [@throws](https://project.pages.drupalcode.org/coding_standards/php/documentation/#throws-thrown-exceptions)
- **Ports:** `Drupal.Commenting.FunctionComment`: `ParamCommentIndentation`, `ParamCommentNewLine`, `ParamNameDot`, `ParamTypeSpaces`, `ReturnCommentIndentation`, `ReturnTypeSpaces`, `ReturnVarName`, `SpacingAfterParamType`, `ThrowsComment`, `ThrowsCommentIndentation`

> The `@param` tag is followed by an optional data type indicator, then the variable name of the
> parameter, and then a newline.
>
> The documentation is indented two spaces (see example).
>
> The name of an exception class should appear on the same line as the `@throws` tag. Put
> explanations in starting on the next line, but only if they tell you something useful that is not
> conveyed by the name of the class.

**Reports**, in the docblock of a function or method, with a fix where the list says so:

- `@param`:
    - a description on the tag's line, which is moved below by the fix, and one not indented three
      spaces from the star, which is indented by the fix;
    - a period after the variable name, and not exactly one space between the type and the
      variable, both fixed;
    - a type with a space;
- `@return`: a description not indented three spaces from the star, which is indented by the fix. A
  variable name after the type, which is removed by the fix when a description follows below. A type
  with a space;
- `@throws`: a description on the tag's line, which is moved by the fix to the line below, three
  spaces from the star. A description not indented three spaces, which is indented by the fix.

A `@return` variable name is read from the tag's own line.

???+ example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported: the @param description on the tag's line.
    /**
     * Builds the label.
     *
     * @param int $id The ID.
     *
     * @return string
     *   The label.
     */
    function example_label(int $id): string {
      return (string) $id;
    }

    // Fine.
    /**
     * Builds the label.
     *
     * @param int $id
     *   The ID.
     *
     * @return string
     *   The label.
     */
    function example_label(int $id): string {
      return (string) $id;
    }
    ```

??? note "Compared with Coder"

    - A `@param` type is the text before the variable, and a `@return` type is the text on the
      tag's line, as in Coder. Both skip a type with a bracket. A `@return` type with a space is
      reported only when a description follows below, and only when the docblock has one
      `@return`. A non-breaking space isn't whitespace to Coder or to this rule.
    - A `@return` variable name is a type and one variable on the line, as in Coder, so
      `callable(int $a): int` isn't one.
    - For `@throws` text on the tag's line with nothing below, Coder counts the words on the line.
      It reports a type such as `\Foo2Bar` or `\A|\B` that has no text, and stops at the first
      `@throws` with no text below it. This rule reports only text after the type, and checks every
      `@throws`. A fix for that text or for the `@throws` indent doesn't exist in Coder. The fix
      here isn't offered for a tag that shares its line with the closing `*/`.
    - A `@param` line with only trailing whitespace after the variable isn't reported. Coder reads
      that whitespace as a description on the tag's line.
    - A run of dots after the variable name, as in `$names...`, is reported with no fix, because it
      can be a variadic written out. phpcbf removes the dots. A name followed by a comma, as in
      `$a,...`, isn't reported. Coder reports it.

---

## drupal/doc-tag-types

- **Level:** error
- **Fix:** safe. It moves a type written after the variable in front of it.
- **Standard:** [@param](https://project.pages.drupalcode.org/coding_standards/php/documentation/#param-function-parameters),
  [@return](https://project.pages.drupalcode.org/coding_standards/php/documentation/#return-function-return-values)
- **Ports:** `Drupal.Commenting.FunctionComment`: `DuplicateReturn`, `MissingParamName`, `MissingParamType`, `MissingReturnType`

> The `@param` tag is followed by an optional data type indicator, then the variable name of the
> parameter, and then a newline.
>
> Data types are required to be included as of Drupal 8.x.

**Reports**, in the docblock of a function or method:

- a `@param` with no variable name, and one with no type. A `&` or `...` in front of the variable
  belongs to the variable, so `@param &$a` has no type. On a tag with no type, a single word after
  the variable is the type. The fix moves that word in front of the variable when it is made of
  type characters, as in `@param $a int`;
- a `@return` with no type on its line;
- more than one `@return`.

??? note "Compared with Coder"

    - phpcbf moves any single word after the variable of a tag with no type, so `@param $a Done.`
      becomes `@param Done. $a`. The fix here moves only a word made of type characters. It moves
      the word as written, so `@param $a integer` becomes `@param integer $a`, and a second
      `--fix` run writes `int`, the name asked for by
      [`drupal/doc-type-name`](#drupaldoc-type-name).
    - A bare `@return` that is the last tag is left to Mago's `valid-docblock`. A `@return 0` has a
      type here. Coder reports it, because PHP's `empty("0")` is true.
    - A `@param` indented inside an `@code` example isn't a tag here, or for the other rules that
      read the tags. Coder reads it as one.

---

## drupal/doc-type-name

- **Level:** error
- **Fix:** safe. It writes the expected name and drops the punctuation, when the type starts the
  tag's line.
- **Standard:** [Data types in documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#data-types-in-documentation)
- **Ports:**
    - `Drupal.Commenting.FunctionComment`: `IncorrectParamVarName`, `InvalidReturn`
    - `Drupal.Commenting.VariableComment.IncorrectVarType` (partly)

> Spell the following types accordingly
>
> * array (NOT "Array").
> * bool (NOT "boolean" or "Boolean"). If only TRUE or only FALSE is a possible value, rather than
>   either one being possible, use true or false instead of bool.
> * true (NOT "TRUE", see bool)
> * false (NOT "FALSE", see bool)
> * int (NOT "integer")
> * null (NOT "NULL")
> * object (NOT "stdClass")

**Reports:**

- a `@param` or `@return` type name of a function or method, and a `@var` type name of a property,
  spelled another way than the standard and Coder ask, such as `integer` for `int`, `Boolean` for
  `bool` or `NULL` for `null`. Each member of a `|` union is checked;
- one of these types that ends in `.`, `;` or `!`, such as `@var string.` or `@return int;`.

**Not reported:**

- `stdClass`, which isn't checked by Coder either;
- a type that ends in `,`, which Coder keeps, or in the `...` of a variadic parameter;
- a `@param` type with a space in it, and a type inside a generic, such as `array<integer>`;
- a `@var` tag with no type or one that starts with a variable name, which are reported by
  [`drupal/variable-comment`](#drupalvariable-comment), and a property docblock with
  `{@inheritdoc}`.

??? note "Compared with Coder"

    - Coder runs the whole `@var` line, description included, through its type check, and reports
      a line with a character outside its type alphabet, such as `.`, `;`, `!` or `=`. So a
      description that contains `e.g.` or ends in a full stop is reported, and so is a float
      literal type such as `1.5|2.5`. The rule only checks a `.`, `;` or `!` at the end of the type.
    - Coder reads `@param int. $a` as a tag with no variable name, since its type stops at the
      first `.`. The rule reports the type and its fix drops the `.`.
    - The rule reads the `@var` type apart from the description. It reports
      `@var integer Some description`, where Coder takes the whole text as the type and doesn't
      report it.
    - In a `@param` or `@return` type, Coder drops every character outside its type alphabet, such
      as a non-breaking space between `int` and `string`, reports the type as `intstring`, and
      phpcbf writes that. The rule doesn't report it.

---

## drupal/file-comment

- **Level:** error
- **Fix:** safe for the `@file` tag and the blank lines. Potentially unsafe for moving the tag, for
  deleting the docblock of a namespaced class file, and for the
  [comment style](#comment-style-fixes).
- **Standard:** [@file](https://project.pages.drupalcode.org/coding_standards/php/documentation/#file-documenting-files)
- **Ports:**
    - `Drupal.Commenting.FileComment`: `FileTag`, `NamespaceNoFileDoc`, `SpacingAfterComment`, `WrongStyle` (partly)
    - `SlevomatCodingStandard.TypeHints.DeclareStrictTypes.IncorrectWhitespaceBeforeDeclare`

> Files that comply with the PSR-4 standard SHOULD NOT have a @file documentation block.
>
> The docblock must be followed by a blank line, and must not contain `@defgroup` or `@mainpage`
> tags.

**Reports**, on the comment at the start of a file:

- a docblock with no `@file` tag. A tag that isn't exactly `@file`, such as `@FILE` or `@File`,
  counts as no tag. The fix adds `@file` below the opener when a blank line parts the docblock from
  the code;
- no blank line between the docblock and the code. This covers a file docblock right above
  `declare`. The fix adds it, and `mago format` turns more than one into one;
- an `@file` tag that isn't on the line right below the opener. A potentially unsafe fix moves a tag
  that stands alone on its line, when the opener is alone on its line too;
- a comment in the wrong style. A `@codingStandardsIgnoreFile` line stays a directive;
- a comment at the start of a file with a `namespace` statement and exactly one class, interface,
  trait or enum. This check runs on every file read by Mago, `.php` files included. The fix deletes
  a docblock at the start, with the whitespace after it. The text of the docblock is lost, so the
  fix is potentially unsafe. A plain comment gets no fix;
- no blank line between a `declare(strict_types=1)` that is the first statement and the comment
  right above it, as `SlevomatCodingStandard.TypeHints.DeclareStrictTypes` asks. Any comment counts
  here, a directive such as `// @mago-expect` too, and the check runs on a class file too. The fix
  adds the blank line.

The comment is the first one after the first open tag, past any directive. A file that doesn't start
with one is reported by [`drupal/missing-file-comment`](#drupalmissing-file-comment).

**Not reported:**

- a class file, which is skipped by the checks above, except the last two. Which files count as
  class files is in the entry of `drupal/missing-file-comment`;
- a docblock at the start of a namespaced file with two or more classes, interfaces, traits or
  enums;
- no blank line above `declare` when the comment there already has a report above: a comment that
  must go, a comment in the wrong style, or a file docblock with no blank line after it.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php title="example.php"
    // Reported.
    /**
     * Helpers for the example module.
     */

    function example_helper(): void {
    }

    // Fine.
    /**
     * @file
     * Helpers for the example module.
     */

    function example_helper(): void {
    }
    ```

??? note "Compared with Coder"

    - A directive at the start of the file, such as `// phpcs:disable` or `// @mago-expect`, is
      skipped, and the comment below it is checked. Coder finds no file comment after a `phpcs:`
      directive, so it reports nothing in a namespaced class file.
    - The report of a missing `@file` sits on the second line of the docblock, where it is put by
      Coder, or on the opener when the docblock has no star line. Coder puts it on the first line of
      the file in that case.
    - A blank line between the docblock and a `?>` that follows it isn't reported. Coder reports it
      (`TemplateSpacingAfterComment`).

---

## drupal/function-comment

- **Level:** error
- **Fix:** safe for the blank line. Potentially unsafe for the
  [comment style](#comment-style-fixes).
- **Standard:** [General considerations for API module parsing](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-considerations-for-api-module-parsing)
- **Ports:** `Drupal.Commenting.FunctionComment`: `SpacingAfter`, `WrongStyle`

> In-code comment lines starting with `//` and comment blocks starting with `/*` are not recognized
> as a docblock.
>
> To document a function, class, etc., the docblock must appear directly before the item being
> documented, with no blank line in between (see example above).

**Reports**, on a function or method:

- a comment in the wrong style where the docblock belongs. A docblock above or below the
  attributes counts;
- a blank line between the docblock and the function, which is removed by the fix.

[`drupal/doc-tag-descriptions`](#drupaldoc-tag-descriptions),
[`drupal/doc-tag-layout`](#drupaldoc-tag-layout), [`drupal/doc-tag-types`](#drupaldoc-tag-types),
[`drupal/doc-type-name`](#drupaldoc-type-name), [`drupal/doc-see-tag`](#drupaldoc-see-tag) and
[`drupal/doc-sentence`](#drupaldoc-sentence) check the tags of the docblock, and
[`drupal/missing-function-comment`](#drupalmissing-function-comment) a function with no docblock.

**Not reported:**

- a blank line below a docblock tagged `@file`, which belongs to the file.

---

## drupal/hook-comment

- **Level:** warning
- **Fix:** potentially unsafe. It replaces a description that repeats the function name.
- **Standard:** [Hook implementation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#hook-implementation)
- **Ports:** `Drupal.Commenting.HookComment`: `HookCommentFormat`, `HookParamDoc`, `HookRepeat`, `HookReturnDoc`

> Documentation uses a short format:
>
> ```php
> /**
>  * Implements hook_help().
>  */
> function blog_help($section) {
> ```
>
> Do not document parameters and return value though -- these are documented on the hook definition
> referenced in the summary line.

**Reports:**

- a hook implementation that isn't documented as `Implements hook_foo().`, such as one that repeats
  the function name, as in `Implements my_mod_help().`;
- a hook implementation with `@param` or `@return` documentation, which repeats the hook's.

The rule reads functions declared at the top level of a file, and only a docblock that touches the
`function` keyword.

**Not reported:**

- a function in a block, in `namespace X { }` or in another function;
- a docblock that has a comment, an attribute or a `?>` between it and the `function` keyword.

The rule also reads a method with a `#[Hook]` attribute, which is skipped by Coder, and applies the
same summary, `@param` and `@return` checks as for a function. The standard predates OOP hooks and
doesn't mention methods. Core writes the `Implements hook_foo().` summary on nearly all of its
`#[Hook]` methods, and the change records for OOP hooks show it too, so the rule asks for it. A
method's docblock may sit above or below its attributes. With `--core` the rule leaves methods out,
as core's phpcs does.

The fix replaces a description that repeats the function name, as in `Implements my_mod_help().`,
with `Implements hook_help().`. For a hook with a placeholder it writes the function's own words,
as in `hook_node_insert()` for `hook_ENTITY_TYPE_insert()`. It applies in a procedural file, when
the function name starts with the file's machine name and an underscore.

??? example "Example"

    ```php
    // Reported.
    /**
     * Implements hook_help().
     *
     * @param string $route_name
     *   The route name.
     */
    function example_help($route_name) {
    }

    // Fine.
    /**
     * Implements hook_help().
     */
    function example_help($route_name) {
    }
    ```

??? note "Compared with Coder"

    - The rule joins the lines of a short description with nothing between them, and a tag on the
      line right below joins in too. So `Implements foo()` with `.` on the next line is a repeat,
      and a `@param` right below `Implements foo().` isn't.
    - Trailing whitespace after the full stop doesn't hide a repeat. Coder misses it.
    - phpcbf takes the machine name up to the first underscore of the function name, so it writes
      `hook_mod_help` for `my_mod_help` in `my_mod.module`, where the rule writes `hook_help`.
    - Coder reports a description that repeats the function name as an error. The rule reports it
      at warning level.

---

## drupal/inline-variable-comment

- **Level:** warning
- **Fix:** potentially unsafe, because the analyzers start to trust the type.
- **Standard:** [In-line code comments](https://project.pages.drupalcode.org/coding_standards/php/documentation/#in-line-code-comments)
- **Ports:** `Drupal.Commenting.InlineVariableComment`: `VarInline`, `VarInlineOrder`

> When documenting variables with the `@var` tag note that you should use the special `/** */`
> delimiters with a double opening asterisk. Always use the fully qualified namespace of the class
> or interface, and include the name of the variable at the end.

**Reports:**

- an inline `@var` declaration in a `//` comment, as in
  `// @var \Drupal\node\NodeInterface $node`;
- a `@var` tag that writes the variable name before the type, as in
  `/** @var $node \Drupal\node\NodeInterface */`.

**Not reported:**

- a `//` or `#` comment that holds `*/`, such as a commented-out docblock;
- a comment or docblock when the first code after it, past any other comments, is a declaration
  keyword such as `public`, `const`, `static` or `function`, or `include`, `require` or their
  `_once` forms. A comment in the wrong style there is left to the comment-style check of that
  declaration's rule. On a property, `drupal/variable-comment` reports a `@var` tag that starts
  with a variable name.

One fix moves a variable name written first after the type, when the tag's own line holds a whole
type. The lines below the tag stay as they are. The other turns a comment that holds only the tag,
alone on its line, into a docblock. It skips a comment with more text than the tag, and a `//` line
inside a run of `//` lines.

---

## drupal/missing-class-comment

- **Level:** error
- **Fix:** none
- **Standard:** [Classes and namespaces](https://project.pages.drupalcode.org/coding_standards/php/documentation/#classes-and-namespaces)
- **Ports:** `Drupal.Commenting.ClassComment.Missing`

> All classes and all of their methods (including private methods but excluding constructors) must
> be documented.

**Reports:**

- a class, interface, trait or enum with no docblock. A docblock tagged `@file` doesn't count as the
  class docblock. [`drupal/file-comment`](#drupalfile-comment) checks it. A docblock above or below
  the attributes counts.

[`drupal/class-comment`](#drupalclass-comment) reports a comment in the wrong style.

??? note "Compared with Coder"

    - phpcbf writes an empty docblock for a class that has none, which is then reported by Coder as
      `DocComment.Empty`. The rule has no fix there.

---

## drupal/missing-file-comment

- **Level:** error
- **Fix:** none
- **Standard:** [@file](https://project.pages.drupalcode.org/coding_standards/php/documentation/#file-documenting-files)
- **Ports:** `Drupal.Commenting.FileComment.Missing`

> The @file doc block MUST be present for PHP files, with the following exceptions:
>
> * Files that comply with the PSR-4 standard SHOULD NOT have a @file documentation block.
> * The core \Drupal class's Drupal.php file (which does not have a namespace).

**Reports:**

- a file that doesn't start with a comment. This check reads every file with a PHP open tag, `.php`
  files included. The comment must follow the first open tag. Text before that tag, such as a
  `#!/usr/bin/env php` line or a byte order mark, which is reported by `drupal/byte-order-mark`,
  doesn't count. A directive such as `// phpcs:ignoreFile` is skipped.

[`drupal/file-comment`](#drupalfile-comment) checks the comment that is there: its style and its
`@file` tag.

**Not reported:**

- a class file. Which files count as class files follows Coder: a file that holds a class,
  interface, trait or enum and a `namespace` statement, and a file that holds exactly one class,
  interface, trait or enum, no function or method outside it, and no `@file` tag in any docblock.
  A closure or an arrow function doesn't count as a function. A method of an anonymous class outside
  the class does.

??? note "Compared with Coder"

    - A directive at the start of the file, such as `// phpcs:disable` or `// @mago-expect`, is
      skipped, and the comment below it counts. Coder finds no file comment after a `phpcs:`
      directive, so it reports a missing one in a file that isn't a class file. It takes a
      `@codingStandardsIgnore`, `@mago-`, `@phpstan-` or `@psalm-` comment for the file comment.
    - phpcbf writes an empty stub for a missing docblock. The rule has no fix there.

---

## drupal/missing-function-comment

- **Level:** error
- **Fix:** none
- **Standard:** [Classes and namespaces](https://project.pages.drupalcode.org/coding_standards/php/documentation/#classes-and-namespaces)
- **Ports:** `Drupal.Commenting.FunctionComment.Missing`

> All classes and all of their methods (including private methods but excluding constructors) must
> be documented.

**Reports:**

- a function or method with no docblock. A docblock tagged `@file` above a function counts as no
  docblock for the function. A docblock above or below the attributes counts.

[`drupal/function-comment`](#drupalfunction-comment) reports a comment in the wrong style.

**Not reported:**

- a constructor with nothing above it. A constructor with a docblock is checked by the other rules;
- with `--core`, a function outside the directories where that check is run by core's
  `phpcs.xml.dist`: `core/lib`, a module's `src/Plugin`, the `Database`, `Functional`,
  `FunctionalJavascript`, `Kernel`, `KernelTests` and `Unit` directories, and the `HookOrder` test
  module.

??? note "Compared with Coder"

    - The constructor name is matched in any case, because PHP ignores case in method names. Coder
      matches `__construct` exactly and reports `__CONSTRUCT` as missing a docblock. A function
      named `__construct` outside a class isn't a constructor and is reported. Coder exempts it.
    - phpcbf writes an empty docblock for a function that has none, which is then reported by Coder
      as `DocComment.Empty`. The rule has no fix there.

---

## drupal/missing-property-comment

- **Level:** error
- **Fix:** none
- **Standard:** [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation),
  [@var](https://project.pages.drupalcode.org/coding_standards/php/documentation/#var-class-property-data-types)
- **Ports:** `Drupal.Commenting.VariableComment.Missing`

> Every function, constant, class, interface, class member (function, property, constant), and
> file must be documented, even private class members.
>
> The comment may be omitted if the name of the variable matches the type, or the type with
> "Interface" appended.

**Reports:**

- a class property with no docblock. A docblock above or below the attributes counts, and a
  comment between the attributes and the property is the property's comment.

[`drupal/variable-comment`](#drupalvariable-comment) reports a comment in the wrong style.

The standard lets a property with a native type skip the comment when its name matches the type,
as in `protected EntityTypeManagerInterface $entityTypeManager`, but Coder, core and this rule
still want it.

---

## drupal/missing-summary

- **Level:** error
- **Fix:** none
- **Standard:** [General documentation](https://project.pages.drupalcode.org/coding_standards/php/documentation/#general-documentation)
- **Ports:** `Drupal.Commenting.DocComment.MissingShort` (partly)

> All summaries (the first line of a docblock) must be under 80 characters, start with a capital
> letter, and end with a period (.). They must provide a brief description of what a function does,
> what a class does, what a file contains, etc.

**Reports:**

- a docblock outside a function body that has tags and no summary. The rule looks only at the
  first tag, or at the tag after a leading `@file`. The summary of a file docblock is the text after
  its `@file` tag, so a file docblock that holds only `@file` is reported.

**Not reported:**

- no summary when the first tag, or the tag after a leading `@file`, is `@covers`;
- a bare `@inheritdoc` there, which is reported by [`drupal/doc-comment`](#drupaldoc-comment) as
  missing its braces;
- a docblock whose first tag, or the tag after a leading `@file`, is `@defgroup`, `@addtogroup`,
  `@}` or `@coversDefaultClass`;
- with `--core`, no summary outside the `KernelTests`, `Unit` and `FunctionalJavascript`
  directories, where that check is run by core's `phpcs.xml.dist`.

??? note "Compared with Coder"

    - A `phpcs:` line above the summary is skipped, and the line below it is the summary. Coder
      reports `MissingShort` there.

---

## drupal/variable-comment

- **Level:** error
- **Fix:** safe for the repeated name. Potentially unsafe for a variable name before the type,
  because the analyzers start to trust the type, and for the [comment style](#comment-style-fixes).
- **Standard:** [@var](https://project.pages.drupalcode.org/coding_standards/php/documentation/#var-class-property-data-types)
- **Ports:** `Drupal.Commenting.VariableComment`: `DuplicateVar`, `EmptyVar`, `InlineVariableName`, `MissingVar`, `VarOrder`, `WrongStyle`

> The `@var` tag is used to document the data type of a class property.
>
> Typed class properties may omit the `@var` declaration. It is recommended to use typed properties
> whenever possible.

**Reports**, on a class property:

- a comment in the wrong style where the docblock belongs. A docblock above or below the
  attributes counts, and a comment between the attributes and the property is the property's
  comment;
- no `@var` tag on a property with no native type, more than one, and one that isn't the first tag;
- a `@var` tag with no type;
- a `@var` tag that starts with a variable name, as in `@var $count int`;
- the property name repeated after the `@var` type on the tag's line, also after a type with
  spaces such as `array<string, int>`.

The fix removes a property name repeated after the `@var` type. In a `@var` tag that starts with a
variable name, a fix writes the type first when a whole type follows the name. It drops the
property's own name, and moves any other name after the type.

[`drupal/missing-property-comment`](#drupalmissing-property-comment) reports a property with no
docblock, and [`drupal/doc-type-name`](#drupaldoc-type-name) the `@var` type name.

**Not reported:**

- a docblock with `{@inheritdoc}`.

??? example "Example"

    The fix turns the reported code into the fine code.

    ```php
    // Reported: the repeated property name.
    class Example {

      /**
       * The number of items.
       *
       * @var int $count
       */
      protected $count = 0;

    }

    // Fine.
    class Example {

      /**
       * The number of items.
       *
       * @var int
       */
      protected $count = 0;

    }
    ```

??? note "Compared with Coder"

    - Coder reports a `@var` tag that starts with a variable name as `IncorrectVarType`, and phpcbf
      writes `count int` for `$count int`, which is broken. The rule writes the type first.
    - Coder reports a name after a type with spaces, as in `@var array<string, int> $name`, as
      `IncorrectVarType`, and phpcbf drops the `$` of the name. The rule reports it as a repeated
      name, and the fix removes it.
    - The rule skips a docblock with `{@inheritdoc}` in any letter case, and reads tag names in any
      letter case. Coder skips only `{@inheritdoc}` and `{@inheritDoc}`, and doesn't take `@VAR` for
      `@var`.
    - The fix removes a repeated name only when it is the property's own name, in a declaration of
      one property, followed by a space or the end of the line. It keeps a description after the
      name. phpcbf also removes another name, and drops the description.
    - Coder asks for a `@var` tag on a property whose type is made only of `self`, `parent`,
      `null`, `true` and `false`, such as `?self` or `self|null`, static or not. Coder looks for a
      type name before the property name, and phpcs reads those words as keywords. It accepts
      every other typed property without the tag, such as `int`, `?int`, `array` or `?Foo`. The
      standard lets typed properties leave `@var` out, and this rule follows it.
