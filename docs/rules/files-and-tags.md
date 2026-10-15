# Files and PHP tags

Rules for the bytes of a file and for its open and close tags.

---

## drupal/byte-order-mark

- **Level:** error
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Generic.Files.ByteOrderMark.Found`

Coder asks for no byte order mark at the start of a file. PHP sends the mark to the browser before
any code runs.

**Reports:**

- a UTF-8 or UTF-16 byte order mark at the first bytes of a file, once per file.

**Not reported:**

- a mark further in.

There isn't a fix, because removing the mark changes what the file sends.

`drupal/file-comment` and `drupal/missing-file-comment` skip a byte order mark before the open tag,
so a procedural file with a mark and a correct docblock gets only this rule's report.

---

## drupal/empty-php-tags

- **Level:** warning
- **Fix:** safe. It removes the pair and the line break that PHP drops after `?>`, so the output
  stays the same. An empty `<?= ?>` doesn't have a fix, because PHP rejects it.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Generic.CodeAnalysis.EmptyPHPStatement.EmptyPHPOpenCloseTagsDetected`

Coder asks for no empty pair of PHP tags.

**Reports:**

- a `<?php` or `<?=` tag that a `?>` follows with only whitespace between, as in `<?php ?>`, in any
  position, also inside a function or an alternative-syntax block.

**Not reported:**

- a pair with a comment between the tags.

??? note "Compared with Coder"

    - The sniff has a second code, for a stray `;`. It stays with Mago's `no-noop`, which reports
      the same spot.
    - A pair at the end of a file is reported as in Coder. There Mago's `no-closing-tag` also
      reports the `?>`, as `Zend.Files.ClosingTag` does in Coder.

---

## drupal/file-encoding

- **Level:** warning
- **Fix:** none
- **Source:** Coder's `Drupal` standard
- **Ports:** `Drupal.Files.FileEncoding.InvalidEncoding`

Coder asks for files in UTF-8.

**Reports:**

- a file that isn't valid UTF-8. The report sits on the first open tag or run of inline text, not on
  the bad byte, and the message doesn't name any bytes. The text that PHP drops after a `?>` doesn't
  count as inline text.

**Not reported:**

- a file whose only tags are `<?=`.

??? note "Compared with Coder"

    - The rule checks UTF-8 with a PCRE pattern, where Coder calls `mb_check_encoding()`.
    - Coder has an `allowedEncodings` option. The rule doesn't have one.

---

## drupal/file-start-whitespace

- **Level:** error
- **Fix:** potentially unsafe. It deletes the whitespace, which a template can print.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Squiz.WhiteSpace.SuperfluousWhitespace.StartFile`

Coder asks for no whitespace before the first `<?php` of a file.

**Reports:**

- whitespace before the first `<?php` of a file. Whitespace is ASCII whitespace and Unicode
  separators, Coder's pattern.

**Not reported:**

- text that isn't whitespace, such as a byte order mark, a zero-width space or a `#!` line;
- whitespace before a later open tag of the file.

??? note "Compared with Coder"

    - Coder's fix is safe. This fix is potentially unsafe, because PHP sends the removed text as
      output.

---

## drupal/short-echo-tag

- **Level:** error
- **Fix:** safe. It writes `<?php echo` with one space before the value, and keeps a line break and
  any comment where they are.
- **Source:** Coder's `Drupal` standard
- **Ports:** `Generic.PHP.DisallowShortOpenTag.EchoFound`

Coder asks for `<?php echo` in place of the `<?=` short echo tag.

**Reports:**

- a `<?=` tag that has a value to echo.

**Not reported:**

- an echo tag with no value, which is left to `drupal/empty-php-tags`.

??? note "Compared with Coder"

    - Mago's `no-short-opening-tag` covers the `<?` tag, so the other two codes of the sniff aren't
      ported.
    - Coder's fix keeps the whitespace after the tag as it is, such as two spaces, a tab or a line
      break. This fix turns spaces and tabs after the tag into one space.
    - Mago stops parsing a file that ends right after a bare `<?=`, so no rule sees that file. Coder
      reports it, and its fixer crashes on it.

---

## drupal/tab-character

- **Level:** error
- **Fix:** safe in a comment, potentially unsafe in HTML. It writes 4 spaces for each tab.
- **Standard:** [Indenting and Whitespace](https://project.pages.drupalcode.org/coding_standards/php/coding/#indenting-and-whitespace)
- **Ports:** `Generic.WhiteSpace.DisallowTabIndent`: `NonIndentTabsUsed`, `TabsUsed`

> Use an indent of 2 spaces, with no tabs.

`mago format` writes spaces in the code and in the indent of a comment's lines. It prints the text
of a comment and the HTML outside the PHP tags as written, so the rule reports the tabs left there.

**Reports:**

- a tab in the text of a comment or a docblock, once per line;
- a tab in the HTML outside the PHP tags, once per line.

**Not reported:**

- a tab in the whitespace at the start of a comment's line, which `mago format` replaces;
- a tab in a string or a heredoc, as in Coder;
- a `.yml` file, which Mago parses as HTML. Coder doesn't report tabs there.

The fix in HTML is potentially unsafe, because the tabs are part of the page output, as in a
`<pre>` element or a plain-text mail body.

??? example "Example"

    The fix turns the reported code into the fine code. The gap after `Net:` in the reported comment
    is a tab.

    ```php
    // Reported.
    function example_total(array $prices): int {
      // Net:	the price before tax.
      return array_sum($prices);
    }

    // Fine.
    function example_total(array $prices): int {
      // Net:    the price before tax.
      return array_sum($prices);
    }
    ```

??? note "Compared with Coder"

    - Coder also reports a tab in the whitespace of the code and at the start of a comment's lines.
      `mago format` replaces those.
    - A tab that becomes 4 spaces can push a comment line past 80 characters, which
      [`drupal/comment-line-length`](comment-text.md#drupalcomment-line-length) then reports. phpcbf
      does the same.
    - The fix also changes a tab in an `@code` sample, which can show output that holds a tab.
    - `mago format` copies the whitespace before an `<?php` tag in HTML onto the code after the tag,
      up to the next `?>`. A tab there comes out in front of each statement, and Coder reports
      those lines. The fix replaces the tab in the HTML, and the next `mago format` writes spaces
      in front of the code.
